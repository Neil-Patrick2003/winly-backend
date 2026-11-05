<?php

use App\Models\Circle;
use App\Models\CircleMembership;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->circle = Circle::factory()->create(['owner_id' => $this->owner->id, 'members_count' => 2]);
    CircleMembership::factory()->create(['circle_id' => $this->circle->id, 'user_id' => $this->owner->id]);

    $this->author = User::factory()->create(['username' => 'ben']);
    CircleMembership::factory()->create(['circle_id' => $this->circle->id, 'user_id' => $this->author->id]);

    $this->post = Post::factory()->for($this->author)->create(['caption' => 'Rude post']);
    $this->post->circles()->attach($this->circle->id);
});

function reportPost(Post $post, string $reason = 'harassment', string $status = Report::STATUS_PENDING): Report
{
    return Report::query()->create([
        'reporter_id' => User::factory()->create()->id,
        'reportable_type' => $post->getMorphClass(),
        'reportable_id' => $post->id,
        'reason' => $reason,
        'status' => $status,
    ]);
}

test('the owner sees reported posts on their wall, counted, without who reported them', function () {
    reportPost($this->post, 'harassment');
    reportPost($this->post, 'harassment');
    reportPost($this->post, 'spam');

    // Not on this circle's wall, and already handled: neither shows.
    reportPost(Post::factory()->create());
    reportPost(tap(Post::factory()->create(), fn (Post $other) => $other->circles()->attach($this->circle->id)), 'spam', Report::STATUS_DISMISSED);

    $this->actingAs($this->owner)
        ->get(route('circles.manage', $this->circle))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('reports', 1)
            ->where('reports.0.post_id', $this->post->id)
            ->where('reports.0.times_reported', 3)
            ->where('reports.0.reasons', ['Harassment or bullying', 'Spam or scam'])
            ->where('reports.0.caption', 'Rude post')
            ->where('reports.0.author.username', 'ben')
            ->where('reports.0.author.is_member', true)
            ->missing('reports.0.reporters')
            ->missing('reports.0.reporter'));
});

test('the owner can take a reported post off the wall without deleting it or closing the report', function () {
    $report = reportPost($this->post);

    $this->actingAs($this->owner)
        ->delete(route('circles.posts.remove', [$this->circle, $this->post]))
        ->assertRedirect();

    expect($this->post->circles()->whereKey($this->circle->id)->exists())->toBeFalse()
        ->and(Post::find($this->post->id))->not->toBeNull()
        ->and($report->fresh()->status)->toBe(Report::STATUS_PENDING);

    $this->actingAs($this->owner)
        ->get(route('circles.manage', $this->circle))
        ->assertInertia(fn (Assert $page) => $page->has('reports', 0));
});

test('a post that is not on the wall cannot be removed from it', function () {
    $elsewhere = Post::factory()->create();

    $this->actingAs($this->owner)
        ->delete(route('circles.posts.remove', [$this->circle, $elsewhere]))
        ->assertNotFound();
});

test('ordinary members cannot remove posts from the wall', function () {
    $this->actingAs($this->author)
        ->delete(route('circles.posts.remove', [$this->circle, $this->post]))
        ->assertForbidden();

    expect($this->post->circles()->whereKey($this->circle->id)->exists())->toBeTrue();
});
