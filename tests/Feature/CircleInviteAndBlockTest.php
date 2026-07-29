<?php

use App\Models\Circle;
use App\Models\CircleBlock;
use App\Models\CircleInvitation;
use App\Models\CircleMembership;
use App\Models\Follow;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create(['full_name' => 'Ada Owner']);
    $this->member = User::factory()->create(['full_name' => 'Bea Member']);
    $this->friend = User::factory()->create(['full_name' => 'Cal Friend']);

    $this->circle = Circle::factory()->create([
        'owner_id' => $this->owner->id,
        'name' => 'Morning Sitters',
    ]);

    foreach ([$this->owner, $this->member] as $user) {
        CircleMembership::create([
            'user_id' => $user->id,
            'circle_id' => $this->circle->id,
            'joined_at' => now(),
        ]);
    }
    $this->circle->update(['members_count' => 2]);

    befriend($this->owner, $this->friend);
});

test('the picker offers mutual follows and says where each stands', function () {
    befriend($this->owner, $this->member);

    $this->actingAs($this->owner)
        ->get(route('circles.manage', $this->circle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('candidates', 2)
            ->where('candidates.0.full_name', 'Bea Member')
            ->where('candidates.0.is_member', true)
            ->where('candidates.1.full_name', 'Cal Friend')
            ->where('candidates.1.is_member', false)
            ->where('candidates.1.invite_status', null)
        );
});

test('somebody who only follows one way is not offered', function () {
    $stranger = User::factory()->create();
    Follow::factory()->from($stranger)->to($this->owner)->create();

    $this->actingAs($this->owner)
        ->get(route('circles.manage', $this->circle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('candidates', 1));
});

test('the picker can be searched', function () {
    $this->actingAs($this->owner)
        ->get(route('circles.manage', ['circle' => $this->circle, 'search' => 'Cal']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('candidates', 1)
            ->where('candidates.0.full_name', 'Cal Friend')
        );

    $this->actingAs($this->owner)
        ->get(route('circles.manage', ['circle' => $this->circle, 'search' => 'Nobody']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('candidates', 0));
});

test('the owner can invite somebody', function () {
    $this->actingAs($this->owner)
        ->post(route('circles.invitations.store', $this->circle), [
            'user_id' => $this->friend->id,
        ])
        ->assertRedirect();

    $invitation = CircleInvitation::sole();

    expect($invitation->invitee_id)->toBe($this->friend->id);
    expect($invitation->inviter_id)->toBe($this->owner->id);
    expect($invitation->status)->toBe(CircleInvitation::PENDING);

    // Being asked is not being in.
    expect($this->circle->refresh()->members_count)->toBe(2);
});

test('inviting twice updates the ask rather than stacking a second one', function () {
    CircleInvitation::factory()->create([
        'circle_id' => $this->circle->id,
        'inviter_id' => $this->owner->id,
        'invitee_id' => $this->friend->id,
        'status' => CircleInvitation::DECLINED,
        'responded_at' => now(),
    ]);

    $this->actingAs($this->owner)
        ->post(route('circles.invitations.store', $this->circle), [
            'user_id' => $this->friend->id,
        ])
        ->assertRedirect();

    expect(CircleInvitation::count())->toBe(1);
    expect(CircleInvitation::sole()->status)->toBe(CircleInvitation::PENDING);
    expect(CircleInvitation::sole()->responded_at)->toBeNull();
});

test('somebody already in cannot be invited', function () {
    $this->actingAs($this->owner)
        ->post(route('circles.invitations.store', $this->circle), [
            'user_id' => $this->member->id,
        ])
        ->assertSessionHasErrors('user_id');

    expect(CircleInvitation::count())->toBe(0);
});

test('somebody blocked cannot be invited', function () {
    CircleBlock::create([
        'circle_id' => $this->circle->id,
        'user_id' => $this->friend->id,
        'blocked_by' => $this->owner->id,
    ]);

    $this->actingAs($this->owner)
        ->post(route('circles.invitations.store', $this->circle), [
            'user_id' => $this->friend->id,
        ])
        ->assertSessionHasErrors('user_id');

    expect(CircleInvitation::count())->toBe(0);
});

test('the owner cannot invite themselves', function () {
    $this->actingAs($this->owner)
        ->post(route('circles.invitations.store', $this->circle), [
            'user_id' => $this->owner->id,
        ])
        ->assertSessionHasErrors('user_id');
});

test('an outsider cannot invite anybody', function () {
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->post(route('circles.invitations.store', $this->circle), [
            'user_id' => $this->friend->id,
        ])
        ->assertForbidden();

    expect(CircleInvitation::count())->toBe(0);
});

test('pending invitations are listed and can be taken back', function () {
    $invitation = CircleInvitation::factory()->create([
        'circle_id' => $this->circle->id,
        'inviter_id' => $this->owner->id,
        'invitee_id' => $this->friend->id,
    ]);

    $this->actingAs($this->owner)
        ->get(route('circles.manage', $this->circle))
        ->assertInertia(fn ($page) => $page
            ->has('invitations', 1)
            ->where('invitations.0.user.full_name', 'Cal Friend')
        );

    $this->actingAs($this->owner)
        ->delete(route('circles.invitations.destroy', [$this->circle, $invitation]))
        ->assertRedirect();

    expect(CircleInvitation::count())->toBe(0);
});

test('an invitation belonging to another circle cannot be taken back through this one', function () {
    $other = Circle::factory()->create(['owner_id' => $this->owner->id]);

    $invitation = CircleInvitation::factory()->create([
        'circle_id' => $other->id,
        'inviter_id' => $this->owner->id,
        'invitee_id' => $this->friend->id,
    ]);

    $this->actingAs($this->owner)
        ->delete(route('circles.invitations.destroy', [$this->circle, $invitation]))
        ->assertNotFound();

    expect(CircleInvitation::count())->toBe(1);
});

test('blocking removes the membership and bars the next one', function () {
    $this->actingAs($this->owner)
        ->post(route('circles.blocks.store', [$this->circle, $this->member]))
        ->assertRedirect();

    expect(CircleBlock::count())->toBe(1);
    expect($this->circle->members()->count())->toBe(1);
    expect($this->circle->refresh()->members_count)->toBe(1);
});

test('blocking cancels an invitation still standing', function () {
    $invitation = CircleInvitation::factory()->create([
        'circle_id' => $this->circle->id,
        'inviter_id' => $this->owner->id,
        'invitee_id' => $this->friend->id,
    ]);

    $this->actingAs($this->owner)
        ->post(route('circles.blocks.store', [$this->circle, $this->friend]))
        ->assertRedirect();

    expect($invitation->refresh()->status)->toBe(CircleInvitation::DECLINED);
    expect($invitation->responded_at)->not->toBeNull();
});

test('the owner cannot be blocked from their own circle', function () {
    $this->actingAs($this->owner)
        ->post(route('circles.blocks.store', [$this->circle, $this->owner]))
        ->assertSessionHasErrors('user');

    expect(CircleBlock::count())->toBe(0);
    expect($this->circle->members()->count())->toBe(2);
});

test('a member cannot block anybody', function () {
    $this->actingAs($this->member)
        ->post(route('circles.blocks.store', [$this->circle, $this->owner]))
        ->assertForbidden();

    expect(CircleBlock::count())->toBe(0);
});

test('blocked people are listed and can be unblocked', function () {
    $this->actingAs($this->owner)
        ->post(route('circles.blocks.store', [$this->circle, $this->member]));

    $this->actingAs($this->owner)
        ->get(route('circles.manage', $this->circle))
        ->assertInertia(fn ($page) => $page
            ->has('blocked', 1)
            ->where('blocked.0.full_name', 'Bea Member')
        );

    $this->actingAs($this->owner)
        ->delete(route('circles.blocks.destroy', [$this->circle, $this->member]))
        ->assertRedirect();

    expect(CircleBlock::count())->toBe(0);
});

test('unblocking does not put them back in the circle', function () {
    $this->actingAs($this->owner)
        ->post(route('circles.blocks.store', [$this->circle, $this->member]));

    $this->actingAs($this->owner)
        ->delete(route('circles.blocks.destroy', [$this->circle, $this->member]));

    expect($this->circle->members()->count())->toBe(1);
    expect($this->circle->refresh()->members_count)->toBe(1);
});

test('blocking twice does not double count the member removal', function () {
    $this->actingAs($this->owner)
        ->post(route('circles.blocks.store', [$this->circle, $this->member]));
    $this->actingAs($this->owner)
        ->post(route('circles.blocks.store', [$this->circle, $this->member]));

    expect(CircleBlock::count())->toBe(1);
    expect($this->circle->refresh()->members_count)->toBe(1);
});

test('managing a circle tells the user what happened', function () {
    $cases = [
        fn () => $this->post(route('circles.invitations.store', $this->circle), [
            'user_id' => $this->friend->id,
        ]),
        fn () => $this->post(route('circles.blocks.store', [$this->circle, $this->member])),
        fn () => $this->delete(route('circles.blocks.destroy', [$this->circle, $this->member])),
    ];

    $expected = ['Invitation sent.', 'Member blocked.', 'Member unblocked.'];

    foreach ($cases as $index => $call) {
        $this->actingAs($this->owner);

        $call()->assertRedirect();

        expect(session('inertia.flash_data')['toast']['message'])->toBe($expected[$index]);
    }
});
