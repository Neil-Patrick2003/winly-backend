<?php

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->author = User::factory()->create();
    $this->post = Post::factory()->by($this->author)->create();
    Sanctum::actingAs($this->user);
});

test('guests cannot comment', function () {
    app()['auth']->forgetGuards();

    $this->postJson(route('api.v1.posts.comments.store', $this->post), [
        'text' => 'Nice one.',
    ])->assertUnauthorized();
});

test('a user can comment on a post', function () {
    $this->postJson(route('api.v1.posts.comments.store', $this->post), [
        'text' => 'Proud of you.',
    ])
        ->assertCreated()
        ->assertJsonPath('data.text', 'Proud of you.')
        ->assertJsonPath('data.post_id', $this->post->id)
        ->assertJsonPath('data.author.id', $this->user->id)
        ->assertJsonPath('data.author.full_name', $this->user->full_name)
        ->assertJsonMissingPath('data.author.email');

    expect(Comment::count())->toBe(1);
    expect($this->post->refresh()->comments_count)->toBe(1);
});

test('a comment needs something in it', function () {
    $this->postJson(route('api.v1.posts.comments.store', $this->post), ['text' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('text');

    expect(Comment::count())->toBe(0);
    expect($this->post->refresh()->comments_count)->toBe(0);
});

test('a comment cannot run past the length cap', function () {
    $this->postJson(route('api.v1.posts.comments.store', $this->post), [
        'text' => str_repeat('a', Comment::MAX_TEXT_LENGTH + 1),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('text');
});

test('a user can edit their own comment', function () {
    $comment = Comment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
        'text' => 'First thought.',
    ]);

    $this->patchJson(route('api.v1.comments.update', $comment), [
        'text' => 'Second thought.',
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $comment->id)
        ->assertJsonPath('data.text', 'Second thought.')
        ->assertJsonPath('data.author.id', $this->user->id);

    expect($comment->refresh()->text)->toBe('Second thought.');
});

test('a user cannot edit somebody else\'s comment', function () {
    $comment = Comment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->author->id,
        'text' => 'Mine.',
    ]);

    $this->patchJson(route('api.v1.comments.update', $comment), ['text' => 'Yours.'])
        ->assertForbidden();

    expect($comment->refresh()->text)->toBe('Mine.');
});

test('the post author cannot edit a comment left on their post', function () {
    $comment = Comment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
        'text' => 'Mine.',
    ]);

    Sanctum::actingAs($this->author);

    $this->patchJson(route('api.v1.comments.update', $comment), ['text' => 'Reworded.'])
        ->assertForbidden();

    expect($comment->refresh()->text)->toBe('Mine.');
});

test('a user can delete their own comment', function () {
    $comment = Comment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
    ]);
    $this->post->increment('comments_count');

    $this->deleteJson(route('api.v1.comments.destroy', $comment))
        ->assertOk()
        ->assertJsonPath('data.id', $comment->id)
        ->assertJsonPath('data.post_id', $this->post->id)
        ->assertJsonPath('data.comments_count', 0);

    expect(Comment::count())->toBe(0);
    expect($this->post->refresh()->comments_count)->toBe(0);
});

test('the post author can clear a comment off their own post', function () {
    $comment = Comment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
    ]);
    $this->post->increment('comments_count');

    Sanctum::actingAs($this->author);

    $this->deleteJson(route('api.v1.comments.destroy', $comment))
        ->assertOk()
        ->assertJsonPath('data.comments_count', 0);

    expect(Comment::count())->toBe(0);
});

test('a bystander cannot delete a comment', function () {
    $comment = Comment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->author->id,
    ]);
    $this->post->increment('comments_count');

    $this->deleteJson(route('api.v1.comments.destroy', $comment))
        ->assertForbidden();

    expect(Comment::count())->toBe(1);
    expect($this->post->refresh()->comments_count)->toBe(1);
});

test('deleting one comment leaves the rest of the thread standing', function () {
    $mine = Comment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
    ]);
    $theirs = Comment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->author->id,
    ]);
    $this->post->increment('comments_count', 2);

    $this->deleteJson(route('api.v1.comments.destroy', $mine))
        ->assertOk()
        ->assertJsonPath('data.comments_count', 1);

    expect(Comment::sole()->id)->toBe($theirs->id);
});

test('commenting on an unknown post is a 404', function () {
    $this->postJson(route('api.v1.posts.comments.store', '99999999-9999-9999-9999-999999999999'), [
        'text' => 'Hello?',
    ])->assertNotFound();
});
