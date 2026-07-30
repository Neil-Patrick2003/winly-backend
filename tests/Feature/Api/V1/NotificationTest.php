<?php

use App\Models\Comment;
use App\Models\Notification;
use App\Models\Post;
use App\Models\PushToken;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create(['full_name' => 'Ada Lovelace']);
    Sanctum::actingAs($this->user);
});

test('guests are turned away', function () {
    app()['auth']->forgetGuards();

    $this->getJson(route('api.v1.notifications.index'))->assertUnauthorized();
});

test('following somebody tells them, and points at who did it', function () {
    $them = User::factory()->create();

    $this->postJson(route('api.v1.users.follow', $them))->assertCreated();

    Sanctum::actingAs($them);
    $rows = collect($this->getJson(route('api.v1.notifications.index'))->assertOk()->json('data'));

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['type'])->toBe('follow')
        ->and($rows[0]['message'])->toBe('Ada Lovelace started following you')
        ->and($rows[0]['actor']['id'])->toBe($this->user->id)
        // A follow has no post: the tap opens the profile instead.
        ->and($rows[0]['post_id'])->toBeNull()
        ->and($rows[0]['is_read'])->toBeFalse();
});

test('liking and commenting tell the author, and carry the post to open', function () {
    $author = User::factory()->create();
    $post = Post::factory()->for($author)->create();

    $this->putJson(route('api.v1.posts.like', $post))->assertCreated();
    $this->postJson(route('api.v1.posts.comments.store', $post), ['text' => 'Well done!'])
        ->assertCreated();

    Sanctum::actingAs($author);
    $rows = collect($this->getJson(route('api.v1.notifications.index'))->assertOk()->json('data'));

    expect($rows->pluck('type')->sort()->values()->all())->toBe(['comment', 'like'])
        ->and($rows->every(fn (array $r): bool => $r['post_id'] === $post->id))->toBeTrue();
});

test('nothing is raised for what you do to your own things', function () {
    $post = Post::factory()->for($this->user)->create();

    $this->putJson(route('api.v1.posts.like', $post))->assertCreated();
    $this->postJson(route('api.v1.posts.comments.store', $post), ['text' => 'Note to self.'])
        ->assertCreated();

    expect(Notification::count())->toBe(0);
});

test('fidgeting with follow or like does not stack notices', function () {
    $them = User::factory()->create();
    $post = Post::factory()->for($them)->create();

    foreach (range(1, 3) as $ignored) {
        $this->postJson(route('api.v1.users.follow', $them));
        $this->deleteJson(route('api.v1.users.unfollow', $them));
        $this->putJson(route('api.v1.posts.like', $post));
        $this->deleteJson(route('api.v1.posts.unlike', $post));
    }

    // One line per thing that happened, not one per tap.
    expect(Notification::where('type', 'follow')->count())->toBe(1)
        ->and(Notification::where('type', 'like')->count())->toBe(1);
});

test('two comments are two notices, because they are two things said', function () {
    $author = User::factory()->create();
    $post = Post::factory()->for($author)->create();

    $this->postJson(route('api.v1.posts.comments.store', $post), ['text' => 'First.'])->assertCreated();
    $this->postJson(route('api.v1.posts.comments.store', $post), ['text' => 'Second.'])->assertCreated();

    expect(Notification::where('type', 'comment')->count())->toBe(2)
        ->and(Comment::count())->toBe(2);
});

test('the unread count drives the badge, and reading clears it', function () {
    $them = User::factory()->create();
    Notification::factory()->count(3)->create(['user_id' => $this->user->id, 'is_read' => false]);
    Notification::factory()->create(['user_id' => $them->id, 'is_read' => false]);

    $this->getJson(route('api.v1.notifications.unread'))
        ->assertOk()
        ->assertJsonPath('data.unread', 3);

    $this->postJson(route('api.v1.notifications.read'))
        ->assertOk()
        ->assertJsonPath('data.unread', 0);

    $this->getJson(route('api.v1.notifications.unread'))->assertJsonPath('data.unread', 0);

    // Read is not gone: the list still holds the record of what happened.
    $this->getJson(route('api.v1.notifications.index'))->assertOk()->assertJsonCount(3, 'data');

    // And somebody else's count was never ours to clear.
    expect(Notification::where('user_id', $them->id)->unread()->count())->toBe(1);
});

test('you can only delete your own notification', function () {
    $theirs = Notification::factory()->create();

    $this->deleteJson(route('api.v1.notifications.destroy', $theirs))->assertNotFound();

    $mine = Notification::factory()->create(['user_id' => $this->user->id]);
    $this->deleteJson(route('api.v1.notifications.destroy', $mine))->assertOk();

    expect(Notification::whereKey($mine->id)->exists())->toBeFalse();
});

test('a device can register for push, and re-registering moves it to the new owner', function () {
    $this->postJson(route('api.v1.push-tokens.store'), [
        'token' => 'ExponentPushToken[abc123]',
        'platform' => 'android',
    ])->assertCreated();

    expect(PushToken::sole())
        ->token->toBe('ExponentPushToken[abc123]')
        ->user_id->toBe($this->user->id);

    // The same install signing in as somebody else must follow them, not leave
    // the previous account receiving their notifications.
    $other = User::factory()->create();
    Sanctum::actingAs($other);
    $this->postJson(route('api.v1.push-tokens.store'), [
        'token' => 'ExponentPushToken[abc123]',
    ])->assertCreated();

    expect(PushToken::count())->toBe(1)
        ->and(PushToken::sole()->user_id)->toBe($other->id);
});

test('a notification is pushed to every live device of its recipient', function () {
    Http::fake(['exp.host/*' => Http::response(['data' => []])]);

    $author = User::factory()->create();
    PushToken::create(['user_id' => $author->id, 'token' => 'ExponentPushToken[phone]']);
    PushToken::create(['user_id' => $author->id, 'token' => 'ExponentPushToken[tablet]']);
    // A dead one must be left out.
    PushToken::create([
        'user_id' => $author->id,
        'token' => 'ExponentPushToken[old]',
        'failed_at' => now(),
    ]);

    $post = Post::factory()->for($author)->create();
    $this->putJson(route('api.v1.posts.like', $post))->assertCreated();

    Http::assertSent(function ($request) {
        $sent = collect($request->data());

        return $sent->count() === 2
            && $sent->pluck('to')->all() === ['ExponentPushToken[phone]', 'ExponentPushToken[tablet]']
            // The payload the app reads to decide where a tap should land.
            && $sent->every(fn (array $m): bool => $m['data']['type'] === 'like')
            && $sent->every(fn (array $m): bool => $m['data']['post_id'] !== null);
    });
});

test('a device Expo says is gone stops being sent to', function () {
    Http::fake([
        'exp.host/*' => Http::response([
            'data' => [['status' => 'error', 'details' => ['error' => 'DeviceNotRegistered']]],
        ]),
    ]);

    $author = User::factory()->create();
    PushToken::create(['user_id' => $author->id, 'token' => 'ExponentPushToken[uninstalled]']);

    $this->postJson(route('api.v1.users.follow', $author))->assertCreated();

    expect(PushToken::sole()->failed_at)->not->toBeNull();
});

test('push failing never takes the notification down with it', function () {
    Http::fake(['exp.host/*' => fn () => throw new RuntimeException('network is down')]);

    $author = User::factory()->create();
    PushToken::create(['user_id' => $author->id, 'token' => 'ExponentPushToken[phone]']);

    // The request still succeeds, and the record is still written.
    $this->postJson(route('api.v1.users.follow', $author))->assertCreated();

    expect(Notification::where('user_id', $author->id)->where('type', 'follow')->count())->toBe(1);
});
