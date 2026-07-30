<?php

use App\Models\Circle;
use App\Models\CircleBlock;
use App\Models\CircleInvitation;
use App\Models\CircleMembership;
use App\Models\Follow;
use App\Models\Post;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);

    $this->circle = Circle::factory()->create(['owner_id' => $this->user->id, 'members_count' => 1]);
    CircleMembership::factory()->create([
        'user_id' => $this->user->id,
        'circle_id' => $this->circle->id,
    ]);
});

test('only friends are offered, and one-way follows are not', function () {
    $friend = User::factory()->create();
    befriend($this->user, $friend);

    // Follows me, but I do not follow back.
    $fan = User::factory()->create();
    Follow::factory()->from($fan)->to($this->user)->create();

    // I follow them, they do not follow back.
    $idol = User::factory()->create();
    Follow::factory()->from($this->user)->to($idol)->create();

    $ids = collect(
        $this->getJson(route('api.v1.circles.friends', $this->circle))->assertOk()->json('data')
    )->pluck('id');

    expect($ids->all())->toBe([$friend->id]);
});

test('a friend can be invited, and the invitation waits on them', function () {
    $friend = User::factory()->create();
    befriend($this->user, $friend);

    $this->postJson(route('api.v1.circles.invitations.store', $this->circle), [
        'user_id' => $friend->id,
    ])->assertCreated()->assertJsonPath('data.status', 'pending');

    // Being asked is not being in.
    expect($this->circle->fresh()->members_count)->toBe(1)
        ->and(CircleMembership::where('user_id', $friend->id)->exists())->toBeFalse();

    $friends = collect(
        $this->getJson(route('api.v1.circles.friends', $this->circle))->assertOk()->json('data')
    );
    expect($friends->firstWhere('id', $friend->id)['invite_status'])->toBe('pending');
});

test('accepting an invitation is what puts you in the circle', function () {
    $friend = User::factory()->create();
    $invitation = CircleInvitation::factory()->create([
        'circle_id' => $this->circle->id,
        'inviter_id' => $this->user->id,
        'invitee_id' => $friend->id,
    ]);

    Sanctum::actingAs($friend);

    // It is waiting on them, so it shows on their alerts.
    $this->getJson(route('api.v1.invitations.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.circle.id', $this->circle->id);

    $this->postJson(route('api.v1.invitations.accept', $invitation))
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');

    expect($this->circle->fresh()->members_count)->toBe(2)
        ->and(CircleMembership::where('user_id', $friend->id)->exists())->toBeTrue();

    // Answered invitations stop being news.
    $this->getJson(route('api.v1.invitations.index'))->assertOk()->assertJsonCount(0, 'data');
});

test('declining leaves you out of the circle', function () {
    $friend = User::factory()->create();
    $invitation = CircleInvitation::factory()->create([
        'circle_id' => $this->circle->id,
        'invitee_id' => $friend->id,
    ]);

    Sanctum::actingAs($friend);

    $this->postJson(route('api.v1.invitations.decline', $invitation))
        ->assertOk()
        ->assertJsonPath('data.status', 'declined');

    expect(CircleMembership::where('user_id', $friend->id)->exists())->toBeFalse();
});

test('nobody else can answer an invitation put to somebody', function () {
    $friend = User::factory()->create();
    $invitation = CircleInvitation::factory()->create([
        'circle_id' => $this->circle->id,
        'inviter_id' => $this->user->id,
        'invitee_id' => $friend->id,
    ]);

    // Not even the person who sent it.
    $this->postJson(route('api.v1.invitations.accept', $invitation))->assertForbidden();

    expect(CircleMembership::where('user_id', $friend->id)->exists())->toBeFalse();
});

test('re-inviting someone who declined updates the ask rather than stacking another', function () {
    $friend = User::factory()->create();
    befriend($this->user, $friend);

    CircleInvitation::factory()->create([
        'circle_id' => $this->circle->id,
        'invitee_id' => $friend->id,
        'status' => CircleInvitation::DECLINED,
        'responded_at' => now(),
    ]);

    $this->postJson(route('api.v1.circles.invitations.store', $this->circle), [
        'user_id' => $friend->id,
    ])->assertOk()->assertJsonPath('data.status', 'pending');

    expect(CircleInvitation::count())->toBe(1);
});

test('somebody already in cannot be invited again', function () {
    $member = User::factory()->create();
    befriend($this->user, $member);
    CircleMembership::factory()->create([
        'user_id' => $member->id,
        'circle_id' => $this->circle->id,
    ]);

    $this->postJson(route('api.v1.circles.invitations.store', $this->circle), [
        'user_id' => $member->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('user_id');
});

test('a non-member cannot invite anybody', function () {
    $outsider = User::factory()->create();
    $friend = User::factory()->create();
    befriend($outsider, $friend);

    Sanctum::actingAs($outsider);

    $this->postJson(route('api.v1.circles.invitations.store', $this->circle), [
        'user_id' => $friend->id,
    ])->assertForbidden();
});

test('the owner can turn a member out', function () {
    $member = User::factory()->create();
    CircleMembership::factory()->create(['user_id' => $member->id, 'circle_id' => $this->circle->id]);
    $this->circle->increment('members_count');

    $this->deleteJson(route('api.v1.circles.members.remove', [$this->circle, $member]))
        ->assertOk()
        ->assertJsonPath('data.members_count', 1);

    expect(CircleMembership::where('user_id', $member->id)->exists())->toBeFalse();
});

test('the owner cannot be removed from their own circle', function () {
    $this->deleteJson(route('api.v1.circles.members.remove', [$this->circle, $this->user]))
        ->assertUnprocessable();
});

test('a member cannot manage other members', function () {
    $member = User::factory()->create();
    CircleMembership::factory()->create(['user_id' => $member->id, 'circle_id' => $this->circle->id]);

    $victim = User::factory()->create();
    CircleMembership::factory()->create(['user_id' => $victim->id, 'circle_id' => $this->circle->id]);

    Sanctum::actingAs($member);

    $this->deleteJson(route('api.v1.circles.members.remove', [$this->circle, $victim]))
        ->assertForbidden();
});

test('blocking removes them, cancels their invitation and keeps them out', function () {
    $trouble = User::factory()->create();
    CircleMembership::factory()->create(['user_id' => $trouble->id, 'circle_id' => $this->circle->id]);
    $this->circle->increment('members_count');
    CircleInvitation::factory()->create([
        'circle_id' => $this->circle->id,
        'invitee_id' => $trouble->id,
    ]);

    $this->postJson(route('api.v1.circles.blocks.store', [$this->circle, $trouble]))
        ->assertOk()
        ->assertJsonPath('data.is_blocked', true)
        ->assertJsonPath('data.members_count', 1);

    expect(CircleMembership::where('user_id', $trouble->id)->exists())->toBeFalse()
        ->and(CircleInvitation::sole()->status)->toBe(CircleInvitation::DECLINED);

    // The front door is shut too, not only the invitation.
    Sanctum::actingAs($trouble);
    $this->postJson(route('api.v1.circles.join', $this->circle))->assertUnprocessable();

    expect($this->circle->fresh()->members_count)->toBe(1);
});

test('a blocked person is not offered for invitation', function () {
    $blocked = User::factory()->create();
    befriend($this->user, $blocked);
    CircleBlock::create(['circle_id' => $this->circle->id, 'user_id' => $blocked->id]);

    $this->postJson(route('api.v1.circles.invitations.store', $this->circle), [
        'user_id' => $blocked->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('user_id');
});

test('unblocking clears the bar without rejoining them', function () {
    $person = User::factory()->create();
    CircleBlock::create(['circle_id' => $this->circle->id, 'user_id' => $person->id]);

    $this->deleteJson(route('api.v1.circles.blocks.destroy', [$this->circle, $person]))
        ->assertOk()
        ->assertJsonPath('data.is_blocked', false);

    expect(CircleMembership::where('user_id', $person->id)->exists())->toBeFalse();

    Sanctum::actingAs($person);
    $this->postJson(route('api.v1.circles.join', $this->circle))->assertOk();
});

test('a circle shows the posts shared into it, and no others', function () {
    $mine = Post::factory()->for($this->user)->create();
    $mine->circles()->attach($this->circle);
    Post::factory()->for($this->user)->create();

    $this->getJson(route('api.v1.circles.posts', $this->circle))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id);
});

test('a win can be shared into a circle you are in, but not one you are not', function () {
    $theirs = Circle::factory()->create();

    $this->postJson(route('api.v1.posts.store'), [
        'circle_ids' => [$theirs->id],
        'wins' => [['type' => 'movement', 'movement_type' => 'walk']],
    ])->assertUnprocessable()->assertJsonValidationErrors('circle_ids.0');

    $this->postJson(route('api.v1.posts.store'), [
        'circle_ids' => [$this->circle->id],
        'wins' => [['type' => 'movement', 'movement_type' => 'walk']],
    ])->assertCreated();

    expect(Post::sole()->circles()->pluck('circles.id')->all())->toBe([$this->circle->id]);
});

test('a circle post reaches everyone, and says which circles carried it', function () {
    $author = User::factory()->create();
    Follow::factory()->from($this->user)->to($author)->create();

    // A circle the reader is not in at all.
    $theirCircle = Circle::factory()->create();
    CircleMembership::factory()->create([
        'user_id' => $author->id,
        'circle_id' => $theirCircle->id,
    ]);

    $open = Post::factory()->for($author)->create();
    $shared = Post::factory()->for($author)->create();
    $shared->circles()->attach($theirCircle);

    $feed = collect($this->getJson(route('api.v1.posts.index'))->assertOk()->json('data'));

    // A circle is where a post is placed, not who it is kept from.
    expect($feed->pluck('id'))->toContain($open->id)
        ->and($feed->pluck('id'))->toContain($shared->id)
        ->and(collect($feed->firstWhere('id', $shared->id)['circles'])->pluck('name')->all())
        ->toBe([$theirCircle->name]);

    // And the thread is open to them too.
    $this->getJson(route('api.v1.posts.show', $shared))->assertOk();
    $this->getJson(route('api.v1.posts.comments.index', $shared))->assertOk();
    $this->postJson(route('api.v1.posts.comments.store', $shared), ['text' => 'Nice one.'])
        ->assertCreated();
});

test('a circle post reaches the feed of everyone who is in the circle', function () {
    $author = User::factory()->create();
    CircleMembership::factory()->create([
        'user_id' => $author->id,
        'circle_id' => $this->circle->id,
    ]);
    $post = Post::factory()->for($author)->create();
    $post->circles()->attach($this->circle);

    $feed = collect($this->getJson(route('api.v1.posts.index'))->assertOk()->json('data'));
    $row = $feed->firstWhere('id', $post->id);

    expect($row)->not->toBeNull()
        // And the card can say where it came from.
        ->and(collect($row['circles'])->pluck('name')->all())->toBe([$this->circle->name]);

    $this->getJson(route('api.v1.posts.show', $post))->assertOk();
});

test('an openly shared post carries no circle', function () {
    $post = Post::factory()->for($this->user)->create();

    $row = collect($this->getJson(route('api.v1.posts.index'))->assertOk()->json('data'))
        ->firstWhere('id', $post->id);

    expect($row['circles'])->toBe([]);
});

test('a circle reports how much has been shared into it', function () {
    Post::factory()->count(3)->for($this->user)->create()
        ->each(fn (Post $post) => $post->circles()->attach($this->circle));
    Post::factory()->for($this->user)->create();

    $this->getJson(route('api.v1.circles.show', $this->circle))
        ->assertOk()
        ->assertJsonPath('data.posts_count', 3);

    $row = collect($this->getJson(route('api.v1.circles.index'))->assertOk()->json('data'))
        ->firstWhere('id', $this->circle->id);

    expect($row['posts_count'])->toBe(3);
});

test('a profile lists everything that person has shared', function () {
    $author = User::factory()->create();
    CircleMembership::factory()->create([
        'user_id' => $author->id,
        'circle_id' => $this->circle->id,
    ]);

    $open = Post::factory()->for($author)->create();
    $shared = Post::factory()->for($author)->create();
    $shared->circles()->attach($this->circle);

    // A circle the reader is not in — which changes nothing.
    $elsewhere = Circle::factory()->create();
    CircleMembership::factory()->create(['user_id' => $author->id, 'circle_id' => $elsewhere->id]);
    $farther = Post::factory()->for($author)->create();
    $farther->circles()->attach($elsewhere);

    $ids = collect($this->getJson(route('api.v1.users.posts', $author))->assertOk()->json('data'))
        ->pluck('id');

    expect($ids)->toContain($open->id)
        ->and($ids)->toContain($shared->id)
        ->and($ids)->toContain($farther->id);
});

test('your own posts stay yours after leaving the circle you shared them into', function () {
    $mine = Post::factory()->for($this->user)->create();
    $mine->circles()->attach($this->circle);

    $this->deleteJson(route('api.v1.circles.leave', $this->circle))->assertOk();

    // The room is gone, the writing is not.
    $ids = collect($this->getJson(route('api.v1.users.posts', $this->user))->assertOk()->json('data'))
        ->pluck('id');
    expect($ids)->toContain($mine->id);

    $this->getJson(route('api.v1.posts.show', $mine))->assertOk();
});

test('a post shared with many circles is still one post, and appears once', function () {
    $author = User::factory()->create();

    // Ten circles, and the reader is in every one of them alongside the author.
    $circles = Circle::factory()->count(10)->create();
    foreach ($circles as $circle) {
        CircleMembership::factory()->create(['user_id' => $author->id, 'circle_id' => $circle->id]);
        CircleMembership::factory()->create(['user_id' => $this->user->id, 'circle_id' => $circle->id]);
    }

    $post = Post::factory()->for($author)->create();
    $post->circles()->attach($circles->pluck('id'));

    // One row in the feed, not ten — this is the whole point of the pivot being
    // read through an existence check rather than a join.
    $feed = collect($this->getJson(route('api.v1.posts.index'))->assertOk()->json('data'));
    expect($feed->where('id', $post->id))->toHaveCount(1);

    // And once on the author's profile, however many circles carry it.
    $profile = collect(
        $this->getJson(route('api.v1.users.posts', $author))->assertOk()->json('data')
    );
    expect($profile->where('id', $post->id))->toHaveCount(1)
        ->and($profile->firstWhere('id', $post->id)['circles'])->toHaveCount(10);

    // One post, so one comment thread and one set of likes.
    expect(Post::count())->toBe(1);
});

test('one share reaches every circle wall it was sent to', function () {
    $second = Circle::factory()->create();
    CircleMembership::factory()->create(['user_id' => $this->user->id, 'circle_id' => $second->id]);

    $this->postJson(route('api.v1.posts.store'), [
        'circle_ids' => [$this->circle->id, $second->id],
        'caption' => 'Shared with both.',
        'wins' => [['type' => 'movement', 'movement_type' => 'walk']],
    ])->assertCreated();

    $post = Post::sole();

    foreach ([$this->circle, $second] as $circle) {
        $wall = collect($this->getJson(route('api.v1.circles.posts', $circle))->assertOk()->json('data'));
        expect($wall->pluck('id')->all())->toBe([$post->id]);
    }

    expect(Post::count())->toBe(1);
});

test('sharing into circles does not take the post away from anyone', function () {
    $author = User::factory()->create();

    $theirs = Circle::factory()->create();
    CircleMembership::factory()->create(['user_id' => $author->id, 'circle_id' => $theirs->id]);

    $walled = Post::factory()->for($author)->create();
    $walled->circles()->attach($theirs);

    $ids = collect($this->getJson(route('api.v1.posts.index'))->assertOk()->json('data'))->pluck('id');

    // Placed in a circle the reader has never heard of, and still theirs to read.
    expect($ids->filter(fn (string $id): bool => $id === $walled->id))->toHaveCount(1);
});
