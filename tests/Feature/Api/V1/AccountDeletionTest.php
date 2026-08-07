<?php

use App\Models\Comment;
use App\Models\Post;
use App\Models\Story;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    $this->user = User::factory()->create([
        'username' => 'neil_m',
        'email' => 'neil@example.com',
    ]);
});

/** Sign in for real, so the token being revoked is a token that existed. */
function tokenFor(User $user, string $device = 'iPhone 17'): string
{
    return test()->postJson(route('api.v1.login'), [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => $device,
    ])->json('token');
}

test('an account can be deleted with the correct password', function () {
    $token = tokenFor($this->user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson(route('api.v1.user.destroy'), ['password' => 'password'])
        ->assertNoContent();

    // Gone outright, not soft deleted — the App Store asks for the account to
    // be removed, and the privacy policy says the content goes with it.
    expect(User::withTrashed()->find($this->user->id))->toBeNull();
});

test('deleting takes the account content with it', function () {
    $post = Post::factory()->create(['user_id' => $this->user->id]);
    $comment = Comment::factory()->create(['user_id' => $this->user->id]);
    $story = Story::factory()->create(['user_id' => $this->user->id]);

    $token = tokenFor($this->user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson(route('api.v1.user.destroy'), ['password' => 'password'])
        ->assertNoContent();

    expect(Post::find($post->id))->toBeNull();
    expect(Comment::find($comment->id))->toBeNull();
    expect(Story::find($story->id))->toBeNull();
});

test('deleting revokes every token the account had', function () {
    $phone = tokenFor($this->user, 'iPhone 17');
    $tablet = tokenFor($this->user, 'iPad');

    $this->withHeader('Authorization', "Bearer {$phone}")
        ->deleteJson(route('api.v1.user.destroy'), ['password' => 'password'])
        ->assertNoContent();

    expect(PersonalAccessToken::findToken($phone))->toBeNull();
    expect(PersonalAccessToken::findToken($tablet))->toBeNull();
});

test('the token stops working immediately afterwards', function () {
    $token = tokenFor($this->user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson(route('api.v1.user.destroy'), ['password' => 'password'])
        ->assertNoContent();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson(route('api.v1.user'))
        ->assertUnauthorized();
});

test('the wrong password does not delete the account', function () {
    $token = tokenFor($this->user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson(route('api.v1.user.destroy'), ['password' => 'not-my-password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    expect(User::find($this->user->id))->not->toBeNull();
});

test('the password is required', function () {
    $token = tokenFor($this->user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson(route('api.v1.user.destroy'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    expect(User::find($this->user->id))->not->toBeNull();
});

test('a guest cannot delete an account', function () {
    $this->deleteJson(route('api.v1.user.destroy'), ['password' => 'password'])
        ->assertUnauthorized();

    expect(User::find($this->user->id))->not->toBeNull();
});

test('deleting one account leaves everybody else alone', function () {
    $other = User::factory()->create(['username' => 'someone_else', 'email' => 'other@example.com']);
    $theirPost = Post::factory()->create(['user_id' => $other->id]);

    $token = tokenFor($this->user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson(route('api.v1.user.destroy'), ['password' => 'password'])
        ->assertNoContent();

    expect(User::find($other->id))->not->toBeNull();
    expect(Post::find($theirPost->id))->not->toBeNull();
});
