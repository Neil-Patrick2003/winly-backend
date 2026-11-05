<?php

use App\Models\Circle;
use App\Models\Comment;
use App\Models\Follow;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

beforeEach(function () {
    Storage::fake('public');
    $this->user = User::factory()->create(['password_hash' => 'secret-password']);
});

test('guests cannot delete an account', function () {
    $this->deleteJson(route('api.v1.profile.destroy'), ['password' => 'secret-password'])
        ->assertUnauthorized();
});

test('the password has to be given again', function () {
    Sanctum::actingAs($this->user);

    $this->deleteJson(route('api.v1.profile.destroy'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    $this->deleteJson(route('api.v1.profile.destroy'), ['password' => 'wrong'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    $this->assertModelExists($this->user);
});

test('deleting an account removes the person for good', function () {
    $token = $this->user->createToken('phone')->plainTextToken;
    $post = Post::factory()->for($this->user)->create();
    Comment::factory()->for($post)->create();

    $this->withToken($token)
        ->deleteJson(route('api.v1.profile.destroy'), ['password' => 'secret-password'])
        ->assertNoContent();

    expect(User::withTrashed()->find($this->user->id))->toBeNull()
        ->and(Post::find($post->id))->toBeNull()
        ->and(Comment::count())->toBe(0)
        ->and($this->user->tokens()->count())->toBe(0);
});

test('other people keep correct counts', function () {
    $friend = User::factory()->create(['followers_count' => 1, 'following_count' => 1]);
    Follow::factory()->create(['follower_id' => $this->user->id, 'followee_id' => $friend->id]);
    Follow::factory()->create(['follower_id' => $friend->id, 'followee_id' => $this->user->id]);

    $theirPost = Post::factory()->for($friend)->create(['likes_count' => 2, 'comments_count' => 3]);
    PostLike::factory()->create(['post_id' => $theirPost->id, 'user_id' => $this->user->id]);
    Comment::factory()->count(2)->create(['post_id' => $theirPost->id, 'user_id' => $this->user->id]);

    Sanctum::actingAs($this->user);

    $this->deleteJson(route('api.v1.profile.destroy'), ['password' => 'secret-password'])
        ->assertNoContent();

    $friend->refresh();
    $theirPost->refresh();

    expect($friend->followers_count)->toBe(0)
        ->and($friend->following_count)->toBe(0)
        ->and($theirPost->likes_count)->toBe(1)
        ->and($theirPost->comments_count)->toBe(1);
});

test('their photos are removed from the disk', function () {
    Sanctum::actingAs($this->user);

    $this->post(route('api.v1.profile.update'), [
        '_method' => 'PATCH',
        'avatar' => UploadedFile::fake()->image('me.jpg'),
    ])->assertOk();

    $this->post(route('api.v1.stories.store'), ['image' => UploadedFile::fake()->image('story.jpg')])
        ->assertCreated();

    $this->post(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [['type' => 'movement', 'movement_type' => 'walk',
            'media' => [UploadedFile::fake()->image('walk.jpg')]]],
    ])->assertCreated();

    $someoneElse = User::factory()->create();
    $someoneElse->addMedia(UploadedFile::fake()->image('them.jpg'))->toMediaCollection(User::AVATAR_COLLECTION);

    expect(Media::count())->toBe(4);

    $this->deleteJson(route('api.v1.profile.destroy'), ['password' => 'secret-password'])
        ->assertNoContent();

    expect(Media::count())->toBe(1)
        ->and(Media::sole()->model_id)->toBe($someoneElse->id)
        ->and(Storage::disk('public')->allFiles())->toHaveCount(1);
});

test('circles they made stay for everyone else', function () {
    $circle = Circle::factory()->create(['owner_id' => $this->user->id]);

    Sanctum::actingAs($this->user);

    $this->deleteJson(route('api.v1.profile.destroy'), ['password' => 'secret-password'])
        ->assertNoContent();

    expect($circle->fresh())->not->toBeNull()
        ->and($circle->fresh()->owner_id)->toBeNull();
});
