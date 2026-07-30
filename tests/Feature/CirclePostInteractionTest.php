<?php

use App\Models\Circle;
use App\Models\CircleMembership;
use App\Models\Comment;
use App\Models\PostLike;
use App\Models\User;
use App\Models\WinLearning;
use App\Models\WinMedia;
use App\Models\WinMeditation;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->member = User::factory()->create(['full_name' => 'Bea Member']);
    $this->outsider = User::factory()->create();

    $this->circle = Circle::factory()->create(['owner_id' => $this->owner->id]);

    foreach ([$this->owner, $this->member] as $user) {
        CircleMembership::create([
            'user_id' => $user->id,
            'circle_id' => $this->circle->id,
            'joined_at' => now(),
        ]);
    }
    $this->circle->update(['members_count' => 2]);

    $this->post = postInCircle($this->circle, $this->member, [
        'caption' => 'Two things today.',
    ]);
});

test('a post arrives with its text, wins and files', function () {
    $meditation = WinMeditation::factory()->create([
        'post_id' => $this->post->id,
        'duration_minutes' => 15,
        'completed' => true,
    ]);
    WinLearning::factory()->create([
        'post_id' => $this->post->id,
        'learned_text' => 'Cursor paging beats offset.',
        'reference_source' => 'https://example.com/a',
    ]);

    WinMedia::factory()->forWin($meditation)->create([
        'url' => 'https://example.com/sunrise.jpg',
        'kind' => 'image',
        'position' => 0,
    ]);

    $this->actingAs($this->member)
        ->get(route('circles.posts', $this->circle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('posts.data.0.caption', 'Two things today.')
            ->has('posts.data.0.wins', 2)
            ->where('posts.data.0.wins.0.type', 'meditation')
            ->where('posts.data.0.wins.0.detail.duration_minutes', 15)
            ->where('posts.data.0.wins.1.detail.learned_text', 'Cursor paging beats offset.')
            ->where('posts.data.0.wins.0.media.0.url', 'https://example.com/sunrise.jpg')
            ->where('posts.data.0.wins.0.media.0.kind', 'image')
        );
});

test('a post says whether the reader has liked it', function () {
    PostLike::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->member->id,
    ]);

    $this->actingAs($this->member)
        ->get(route('circles.posts', $this->circle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('posts.data.0.viewer_has_liked', true));

    $this->actingAs($this->owner)
        ->get(route('circles.posts', $this->circle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('posts.data.0.viewer_has_liked', false));
});

test('a post carries the newest few comments and the real total', function () {
    foreach (range(1, 5) as $index) {
        Comment::factory()->create([
            'post_id' => $this->post->id,
            'user_id' => $this->owner->id,
            'text' => "comment {$index}",
            'created_at' => now()->subMinutes(10 - $index),
        ]);
    }
    $this->post->update(['comments_count' => 5]);

    $this->actingAs($this->member)
        ->get(route('circles.posts', $this->circle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('posts.data.0.comments', 3)
            ->where('posts.data.0.comments_count', 5)
            ->where('posts.data.0.comments.0.text', 'comment 5')
            ->where('posts.data.0.comments.2.text', 'comment 3')
        );
});

test('a member can like and unlike a post', function () {
    $this->actingAs($this->member)
        ->put(route('posts.like', $this->post))
        ->assertRedirect();

    expect($this->post->refresh()->likes_count)->toBe(1);

    $this->actingAs($this->member)
        ->delete(route('posts.unlike', $this->post))
        ->assertRedirect();

    expect($this->post->refresh()->likes_count)->toBe(0);
    expect(PostLike::count())->toBe(0);
});

test('liking twice does not count twice', function () {
    $this->actingAs($this->member)->put(route('posts.like', $this->post));
    $this->actingAs($this->member)->put(route('posts.like', $this->post));

    expect($this->post->refresh()->likes_count)->toBe(1);
    expect(PostLike::count())->toBe(1);
});

test('somebody outside the circle can still like a post shared into it', function () {
    // A circle is where a post is placed, not who it is kept from: sharing
    // into one puts the win on that wall without taking it from anybody else.
    $this->actingAs($this->outsider)
        ->put(route('posts.like', $this->post))
        ->assertRedirect();

    expect($this->post->refresh()->likes_count)->toBe(1);
});

test('a member can comment on a post', function () {
    $this->actingAs($this->member)
        ->post(route('posts.comments.store', $this->post), ['text' => 'Well done.'])
        ->assertRedirect();

    expect(Comment::sole()->text)->toBe('Well done.');
    expect($this->post->refresh()->comments_count)->toBe(1);
});

test('an empty comment is rejected', function () {
    $this->actingAs($this->member)
        ->post(route('posts.comments.store', $this->post), ['text' => ''])
        ->assertSessionHasErrors('text');

    expect(Comment::count())->toBe(0);
});

test('somebody outside the circle can still comment', function () {
    $this->actingAs($this->outsider)
        ->post(route('posts.comments.store', $this->post), ['text' => 'Nice one.'])
        ->assertRedirect();

    expect(Comment::sole()->text)->toBe('Nice one.');
});

test('a commenter can delete their own comment', function () {
    $comment = Comment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->owner->id,
    ]);
    $this->post->update(['comments_count' => 1]);

    $this->actingAs($this->owner)
        ->delete(route('comments.destroy', $comment))
        ->assertRedirect();

    expect(Comment::count())->toBe(0);
    expect($this->post->refresh()->comments_count)->toBe(0);
});

test('the post author can clear a comment off their own post', function () {
    $comment = Comment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->owner->id,
    ]);
    $this->post->update(['comments_count' => 1]);

    $this->actingAs($this->member)
        ->delete(route('comments.destroy', $comment))
        ->assertRedirect();

    expect(Comment::count())->toBe(0);
});

test('a bystander cannot delete somebody elses comment', function () {
    $comment = Comment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->member->id,
    ]);

    $this->actingAs($this->outsider)
        ->delete(route('comments.destroy', $comment))
        ->assertForbidden();

    expect(Comment::count())->toBe(1);
});

test('the comment delete button is offered only to those who may use it', function () {
    Comment::factory()->create([
        'post_id' => $this->post->id,
        'user_id' => $this->owner->id,
    ]);

    $this->actingAs($this->owner)
        ->get(route('circles.posts', $this->circle))
        ->assertInertia(fn ($page) => $page->where('posts.data.0.comments.0.can_delete', true));

    $bystander = User::factory()->create();
    CircleMembership::create([
        'user_id' => $bystander->id,
        'circle_id' => $this->circle->id,
        'joined_at' => now(),
    ]);

    $this->actingAs($bystander)
        ->get(route('circles.posts', $this->circle))
        ->assertInertia(fn ($page) => $page->where('posts.data.0.comments.0.can_delete', false));
});

test('listing posts runs the same queries however many there are', function () {
    $measure = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->member)
            ->get(route('circles.posts', $this->circle))
            ->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $few = $measure();

    foreach (range(1, 8) as $ignored) {
        postInCircle($this->circle, $this->member);
    }

    expect($measure())->toBe($few);
});
