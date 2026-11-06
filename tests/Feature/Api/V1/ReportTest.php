<?php

use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\Story;
use App\Models\User;
use App\Notifications\ContentReported;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Notification::fake();
    config(['legal.contact_email' => 'mod@example.com']);

    $this->me = User::factory()->create();
    Sanctum::actingAs($this->me);
});

test('guests cannot report', function () {
    app()['auth']->forgetGuards();

    $this->postJson(route('api.v1.reports.store'), [])->assertUnauthorized();
});

test('a post, comment, story or person can be reported', function (string $type, Closure $make) {
    $target = $make();

    $this->postJson(route('api.v1.reports.store'), [
        'type' => $type,
        'id' => $target->getKey(),
        'reason' => 'harassment',
        'note' => 'Keeps posting this under my wins.',
    ])->assertCreated();

    $report = Report::sole();

    expect($report->reportable->is($target))->toBeTrue()
        ->and($report->reporter_id)->toBe($this->me->id)
        ->and($report->reason)->toBe('harassment')
        ->and($report->note)->toBe('Keeps posting this under my wins.')
        ->and($report->status)->toBe(Report::STATUS_PENDING);

    Notification::assertSentTo(
        new AnonymousNotifiable,
        ContentReported::class,
        fn (ContentReported $notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'mod@example.com',
    );
})->with([
    'post' => ['post', fn () => Post::factory()->create()],
    'comment' => ['comment', fn () => Comment::factory()->create()],
    'story' => ['story', fn () => Story::factory()->create()],
    'user' => ['user', fn () => User::factory()->create()],
]);

test('the moderator email reads cleanly', function () {
    $post = Post::factory()->create(['caption' => 'Buy followers here']);

    $this->postJson(route('api.v1.reports.store'), ['type' => 'post', 'id' => $post->id, 'reason' => 'spam'])
        ->assertCreated();

    $mail = (new ContentReported(Report::sole()))->toMail(new AnonymousNotifiable);

    expect($mail->subject)->toContain('Post reported: Spam or scam')
        ->and(implode("\n", $mail->introLines))->toContain('Buy followers here')
        ->and($mail->actionUrl)->toBe(route('admin.reports'));
});

test('reasons and types are checked', function () {
    $post = Post::factory()->create();

    $this->postJson(route('api.v1.reports.store'), ['type' => 'post', 'id' => $post->id, 'reason' => 'boring'])
        ->assertUnprocessable()->assertJsonValidationErrors('reason');

    $this->postJson(route('api.v1.reports.store'), ['type' => 'circle', 'id' => $post->id, 'reason' => 'spam'])
        ->assertUnprocessable()->assertJsonValidationErrors('type');

    $this->postJson(route('api.v1.reports.store'), ['type' => 'post', 'id' => 'nope', 'reason' => 'spam'])
        ->assertUnprocessable()->assertJsonValidationErrors('id');
});

test('you cannot report yourself or your own things', function () {
    $mine = Post::factory()->for($this->me)->create();

    $this->postJson(route('api.v1.reports.store'), ['type' => 'post', 'id' => $mine->id, 'reason' => 'spam'])
        ->assertUnprocessable()->assertJsonValidationErrors('id');

    $this->postJson(route('api.v1.reports.store'), ['type' => 'user', 'id' => $this->me->id, 'reason' => 'spam'])
        ->assertUnprocessable()->assertJsonValidationErrors('id');
});

test('reporting the same thing again updates the one report', function () {
    $post = Post::factory()->create();

    $this->postJson(route('api.v1.reports.store'), ['type' => 'post', 'id' => $post->id, 'reason' => 'spam'])->assertCreated();
    Report::sole()->update(['status' => Report::STATUS_DISMISSED]);

    $this->postJson(route('api.v1.reports.store'), ['type' => 'post', 'id' => $post->id, 'reason' => 'hate'])->assertCreated();

    expect(Report::count())->toBe(1)
        ->and(Report::sole()->reason)->toBe('hate')
        ->and(Report::sole()->status)->toBe(Report::STATUS_PENDING);
});

test('a failed email does not lose the report', function () {
    Notification::swap(new class
    {
        public function route(): never
        {
            throw new RuntimeException('Brevo is down');
        }
    });

    $post = Post::factory()->create();

    $this->postJson(route('api.v1.reports.store'), ['type' => 'post', 'id' => $post->id, 'reason' => 'spam'])
        ->assertCreated();

    expect(Report::count())->toBe(1);
});

test('a moderator can take reported content down', function () {
    $post = Post::factory()->create();
    $comment = Comment::factory()->create();
    $comment->post->update(['comments_count' => 1]);

    $this->postJson(route('api.v1.reports.store'), ['type' => 'post', 'id' => $post->id, 'reason' => 'spam']);
    $this->postJson(route('api.v1.reports.store'), ['type' => 'comment', 'id' => $comment->id, 'reason' => 'hate']);

    $postReport = Report::query()->where('reportable_id', $post->id)->sole();
    $commentReport = Report::query()->where('reportable_id', $comment->id)->sole();

    $this->artisan('reports:resolve', ['report' => $postReport->id, '--remove' => true])->assertSuccessful();
    $this->artisan('reports:resolve', ['report' => $commentReport->id, '--remove' => true])->assertSuccessful();

    expect(Post::find($post->id))->toBeNull()
        ->and(Comment::find($comment->id))->toBeNull()
        ->and($comment->post->fresh()->comments_count)->toBe(0)
        ->and($postReport->fresh()->status)->toBe(Report::STATUS_ACTIONED)
        ->and($postReport->fresh()->reviewed_at)->not->toBeNull();
});

test('a moderator can dismiss a report and list what is left', function () {
    $post = Post::factory()->create();
    $this->postJson(route('api.v1.reports.store'), ['type' => 'post', 'id' => $post->id, 'reason' => 'spam']);

    $this->artisan('reports:list')->expectsOutputToContain('Spam or scam')->assertSuccessful();

    $this->artisan('reports:resolve', ['report' => Report::sole()->id, '--dismiss' => true])->assertSuccessful();

    expect(Post::find($post->id))->not->toBeNull()
        ->and(Report::sole()->status)->toBe(Report::STATUS_DISMISSED);

    $this->artisan('reports:list')->expectsOutput('No reports waiting.')->assertSuccessful();
});
