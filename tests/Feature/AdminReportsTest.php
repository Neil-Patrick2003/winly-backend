<?php

use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->reporter = User::factory()->create(['username' => 'ana']);
    $this->author = User::factory()->create(['username' => 'ben']);
});

function reportOn(object $target, User $by, string $reason = 'spam'): Report
{
    return Report::query()->create([
        'reporter_id' => $by->id,
        'reportable_type' => $target->getMorphClass(),
        'reportable_id' => $target->getKey(),
        'reason' => $reason,
    ]);
}

test('only staff can open the reports page', function () {
    $this->get(route('admin.reports'))->assertRedirect(route('login'));
    $this->actingAs($this->reporter)->get(route('admin.reports'))->assertNotFound();
});

test('the page lists what is waiting, with what was reported and by whom', function () {
    $post = Post::factory()->for($this->author)->create(['caption' => 'Buy followers']);
    reportOn($post, $this->reporter);
    reportOn($post, User::factory()->create());

    $this->actingAs($this->admin)
        ->get(route('admin.reports'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reports')
            ->where('status', 'pending')
            // Two reports about one post are one card, counted.
            ->where('pendingCount', 1)
            ->has('reports.data', 1)
            ->where('reports.data.0.type', 'post')
            ->where('reports.data.0.times_reported', 2)
            ->where('reports.data.0.reasons', ['Spam or scam'])
            ->where('reports.data.0.target.text', 'Buy followers')
            ->where('reports.data.0.author.username', 'ben')
            ->has('reports.data.0.reporters', 2));
});

test('each reported thing is its own card, with every reason and note', function () {
    $post = Post::factory()->for($this->author)->create();
    $comment = Comment::factory()->for($this->author)->create();

    reportOn($post, $this->reporter, 'spam')->update(['note' => 'Selling followers']);
    reportOn($post, User::factory()->create(), 'hate');
    reportOn($post, User::factory()->create(), 'hate');
    reportOn($comment, $this->reporter, 'harassment');

    $this->actingAs($this->admin)
        ->get(route('admin.reports'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pendingCount', 2)
            ->has('reports.data', 2)
            ->where('reports.data.0.times_reported', 3)
            ->where('reports.data.0.reasons', ['Hate speech', 'Spam or scam'])
            ->where('reports.data.0.notes.0.text', 'Selling followers')
            ->where('reports.data.0.notes.0.by', '@ana')
            ->where('reports.data.1.times_reported', 1));
});

test('dismissing closes every report about the same thing', function () {
    $post = Post::factory()->for($this->author)->create();
    $first = reportOn($post, $this->reporter);
    $second = reportOn($post, User::factory()->create());

    $this->actingAs($this->admin)->post(route('admin.reports.dismiss', $second));

    expect($first->fresh()->status)->toBe(Report::STATUS_DISMISSED)
        ->and($second->fresh()->status)->toBe(Report::STATUS_DISMISSED);
});

test('deleting reported content closes every report about it', function () {
    $post = Post::factory()->for($this->author)->create();
    $first = reportOn($post, $this->reporter);
    $second = reportOn($post, User::factory()->create());

    $this->actingAs($this->admin)
        ->post(route('admin.reports.remove', $first))
        ->assertRedirect();

    expect(Post::find($post->id))->toBeNull()
        ->and($first->fresh()->status)->toBe(Report::STATUS_ACTIONED)
        ->and($first->fresh()->reviewed_by)->toBe($this->admin->id)
        ->and($second->fresh()->status)->toBe(Report::STATUS_ACTIONED);
});

test('a reported comment can be deleted', function () {
    $comment = Comment::factory()->for($this->author)->create();
    $comment->post->update(['comments_count' => 1]);

    $this->actingAs($this->admin)
        ->post(route('admin.reports.remove', reportOn($comment, $this->reporter)));

    expect(Comment::find($comment->id))->toBeNull()
        ->and($comment->post->fresh()->comments_count)->toBe(0);
});

test('disabling from a report switches the author off', function () {
    $post = Post::factory()->for($this->author)->create();
    $this->author->createToken('phone');

    $this->actingAs($this->admin)
        ->post(route('admin.reports.disable', reportOn($post, $this->reporter)))
        ->assertRedirect();

    expect($this->author->fresh()->disabled_at)->not->toBeNull()
        ->and($this->author->tokens()->count())->toBe(0)
        // Disabling hides, it does not delete.
        ->and(Post::find($post->id))->not->toBeNull();
});

test('staff cannot be disabled from a report', function () {
    $other = User::factory()->admin()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.reports.disable', reportOn($other, $this->reporter)))
        ->assertRedirect();

    expect($other->fresh()->disabled_at)->toBeNull();
});

test('dismissing leaves the content alone', function () {
    $post = Post::factory()->for($this->author)->create();
    $report = reportOn($post, $this->reporter);

    $this->actingAs($this->admin)->post(route('admin.reports.dismiss', $report));

    expect(Post::find($post->id))->not->toBeNull()
        ->and($report->fresh()->status)->toBe(Report::STATUS_DISMISSED);
});

test('people can be disabled and enabled from the People page', function () {
    $this->actingAs($this->admin)->post(route('admin.users.disable', $this->author));
    expect($this->author->fresh()->disabled_at)->not->toBeNull();

    $this->actingAs($this->admin)->post(route('admin.users.enable', $this->author));
    expect($this->author->fresh()->disabled_at)->toBeNull();

    $this->actingAs($this->admin)->post(route('admin.users.disable', $this->admin));
    expect($this->admin->fresh()->disabled_at)->toBeNull();
});

test('a disabled account cannot sign in or reach the api', function () {
    $this->author->update(['password_hash' => 'secret-password']);
    $token = $this->author->createToken('phone')->plainTextToken;
    $this->author->forceFill(['disabled_at' => now()])->save();

    $this->postJson(route('api.v1.login'), [
        'email' => $this->author->email,
        'password' => 'secret-password',
        'device_name' => 'phone',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->withToken($token)->getJson(route('api.v1.user'))->assertForbidden();
});

test('a disabled account is hidden from everyone else', function () {
    $post = Post::factory()->for($this->author)->create();
    $this->author->forceFill(['disabled_at' => now()])->save();

    Sanctum::actingAs($this->reporter);

    $this->getJson(route('api.v1.posts.index'))->assertOk()->assertJsonCount(0, 'data');
    $this->getJson(route('api.v1.posts.show', $post))->assertForbidden();
    $this->getJson(route('api.v1.users.show', $this->author))->assertNotFound();
    $this->getJson(route('api.v1.discover', ['q' => 'ben']))->assertJsonMissing(['id' => $this->author->id]);
});

test('enabling an account brings everything back', function () {
    Post::factory()->for($this->author)->create();
    $this->author->forceFill(['disabled_at' => now()])->save();

    $this->actingAs($this->admin)->post(route('admin.users.enable', $this->author));

    Sanctum::actingAs($this->reporter);
    $this->getJson(route('api.v1.posts.index'))->assertJsonCount(1, 'data');
});
