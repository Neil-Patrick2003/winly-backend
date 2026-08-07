<?php

use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\Story;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->author = User::factory()->create();

    Sanctum::actingAs($this->user);
});

test('a post can be reported', function () {
    $post = Post::factory()->create(['user_id' => $this->author->id]);

    $this->postJson(route('api.v1.reports.store'), [
        'type' => 'post',
        'id' => $post->id,
        'reason' => 'harassment',
        'note' => 'Targeting another member.',
    ])
        ->assertCreated()
        ->assertJsonStructure(['message', 'data' => ['id']]);

    $report = Report::sole();

    expect($report->reporter_id)->toBe($this->user->id);
    expect($report->reportable_id)->toBe($post->id);
    expect($report->reportable->is($post))->toBeTrue();
    expect($report->reason)->toBe('harassment');
    expect($report->note)->toBe('Targeting another member.');
    expect($report->status)->toBe(Report::STATUS_PENDING);
});

test('a comment, a story and a person can each be reported', function (string $type) {
    $subject = match ($type) {
        'comment' => Comment::factory()->create(['user_id' => $this->author->id]),
        'story' => Story::factory()->create(['user_id' => $this->author->id]),
        'user' => $this->author,
    };

    $this->postJson(route('api.v1.reports.store'), [
        'type' => $type,
        'id' => $subject->getKey(),
        'reason' => 'spam',
    ])->assertCreated();

    expect(Report::sole()->reportable->is($subject))->toBeTrue();
})->with(['comment', 'story', 'user']);

test('reporting is refused without a reason from the list', function () {
    $post = Post::factory()->create(['user_id' => $this->author->id]);

    $this->postJson(route('api.v1.reports.store'), [
        'type' => 'post',
        'id' => $post->id,
        'reason' => 'i just do not like it',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');

    expect(Report::count())->toBe(0);
});

test('a report cannot be pointed at an arbitrary kind of record', function () {
    $post = Post::factory()->create(['user_id' => $this->author->id]);

    $this->postJson(route('api.v1.reports.store'), [
        'type' => 'circle_membership',
        'id' => $post->id,
        'reason' => 'spam',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('type');
});

test('reporting something that is not there is refused', function () {
    $this->postJson(route('api.v1.reports.store'), [
        'type' => 'post',
        'id' => '019fdb72-ffeb-7384-9dfd-000000000000',
        'reason' => 'spam',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('id');

    expect(Report::count())->toBe(0);
});

test('you cannot report your own content', function () {
    $mine = Post::factory()->create(['user_id' => $this->user->id]);

    $this->postJson(route('api.v1.reports.store'), [
        'type' => 'post',
        'id' => $mine->id,
        'reason' => 'spam',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('id');

    expect(Report::count())->toBe(0);
});

test('you cannot report yourself', function () {
    $this->postJson(route('api.v1.reports.store'), [
        'type' => 'user',
        'id' => $this->user->id,
        'reason' => 'spam',
    ])->assertUnprocessable();

    expect(Report::count())->toBe(0);
});

test('reporting the same thing twice leaves one report and says the same thing', function () {
    $post = Post::factory()->create(['user_id' => $this->author->id]);

    $payload = ['type' => 'post', 'id' => $post->id, 'reason' => 'spam'];

    $this->postJson(route('api.v1.reports.store'), $payload)->assertCreated();
    $this->postJson(route('api.v1.reports.store'), $payload)->assertCreated();

    // Somebody who taps it twice meant it once. Telling them off for the second
    // tap teaches them the button is broken.
    expect(Report::count())->toBe(1);
});

test('two people reporting the same thing are two reports', function () {
    $post = Post::factory()->create(['user_id' => $this->author->id]);
    $payload = ['type' => 'post', 'id' => $post->id, 'reason' => 'spam'];

    $this->postJson(route('api.v1.reports.store'), $payload)->assertCreated();

    Sanctum::actingAs(User::factory()->create());
    $this->postJson(route('api.v1.reports.store'), $payload)->assertCreated();

    expect(Report::count())->toBe(2);
});

test('reporting does not remove or hide the content', function () {
    $post = Post::factory()->create(['user_id' => $this->author->id]);

    $this->postJson(route('api.v1.reports.store'), [
        'type' => 'post',
        'id' => $post->id,
        'reason' => 'violence',
    ])->assertCreated();

    // Reporting is not moderation. Letting one person's report hide a post
    // would hand every account a delete button over everybody else's.
    expect(Post::find($post->id))->not->toBeNull();
});

test('a note longer than the limit is refused', function () {
    $post = Post::factory()->create(['user_id' => $this->author->id]);

    $this->postJson(route('api.v1.reports.store'), [
        'type' => 'post',
        'id' => $post->id,
        'reason' => 'other',
        'note' => str_repeat('a', Report::MAX_NOTE_LENGTH + 1),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('note');
});

test('guests cannot report', function () {
    $post = Post::factory()->create(['user_id' => $this->author->id]);

    // Sanctum::actingAs cannot be undone within a test, so this asks as a fresh
    // unauthenticated request instead.
    $this->app['auth']->forgetGuards();

    $this->postJson(route('api.v1.reports.store'), [
        'type' => 'post',
        'id' => $post->id,
        'reason' => 'spam',
    ])->assertUnauthorized();
});
