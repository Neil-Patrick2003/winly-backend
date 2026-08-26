<?php

use App\Actions\Circles\CreateCircle;
use App\Actions\Circles\TransferCircleOwnership;
use App\Models\Circle;
use App\Models\CircleMembership;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create(['full_name' => 'Ada Owner']);
    $this->second = User::factory()->create(['full_name' => 'Bea Second']);
    $this->member = User::factory()->create(['full_name' => 'Cal Member']);

    $this->circle = Circle::factory()->create([
        'owner_id' => $this->owner->id,
        'name' => 'Morning Sitters',
    ]);

    foreach ([$this->owner, $this->second, $this->member] as $user) {
        CircleMembership::create([
            'user_id' => $user->id,
            'circle_id' => $this->circle->id,
            'role' => $user->is($this->owner)
                ? CircleMembership::ROLE_OWNER
                : CircleMembership::ROLE_MEMBER,
            'joined_at' => now(),
        ]);
    }

    $this->circle->update(['members_count' => 3]);
});

/**
 * Give somebody the run of the circle, the way the screen does.
 */
function makeCoOwner(Circle $circle, User $user): void
{
    $circle->memberships()
        ->where('user_id', $user->id)
        ->update(['role' => CircleMembership::ROLE_OWNER]);
}

test('a membership is an ordinary one unless it is given a rank', function () {
    $membership = CircleMembership::query()
        ->where('user_id', $this->member->id)
        ->sole();

    expect($membership->role)->toBe(CircleMembership::ROLE_MEMBER);
});

test('whoever makes a circle holds the rank in it', function (): void {
    $circle = app(CreateCircle::class)->execute($this->member, ['name' => 'Evening Sitters']);

    expect($circle->memberships()->where('user_id', $this->member->id)->sole()->role)
        ->toBe(CircleMembership::ROLE_OWNER);
});

test('an ordinary member cannot manage the circle', function () {
    $this->actingAs($this->member)
        ->get(route('circles.manage', $this->circle))
        ->assertForbidden();
});

test('a second owner manages the circle exactly as the founder does', function () {
    makeCoOwner($this->circle, $this->second);

    $this->actingAs($this->second)
        ->get(route('circles.manage', $this->circle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('circles/manage'));

    // Renaming, which the policy gates on ownership.
    $this->actingAs($this->second)
        ->patch(route('circles.manage.update', $this->circle), [
            'name' => 'Evening Sitters',
            'icon_initial' => 'E',
            'color_hex' => $this->circle->color_hex,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($this->circle->fresh()->name)->toBe('Evening Sitters');
});

test('a second owner can remove and bar members', function () {
    makeCoOwner($this->circle, $this->second);

    $this->actingAs($this->second)
        ->delete(route('circles.members.remove', [$this->circle, $this->member]))
        ->assertRedirect();

    expect($this->circle->memberships()->where('user_id', $this->member->id)->exists())
        ->toBeFalse();
});

test('a second owner can take the circle down', function () {
    makeCoOwner($this->circle, $this->second);

    $this->actingAs($this->second)
        ->delete(route('circles.destroy', $this->circle))
        ->assertRedirect();

    expect(Circle::query()->whereKey($this->circle->id)->exists())->toBeFalse();
});

test('a second owner sees a private circle they were given the run of', function () {
    $private = Circle::factory()->create([
        'owner_id' => $this->owner->id,
        'is_private' => true,
        'name' => 'Quiet Ones',
    ]);

    // The rank alone, with no membership row: the policy has to read it off
    // the rank rather than off the founder's column.
    CircleMembership::create([
        'user_id' => $this->second->id,
        'circle_id' => $private->id,
        'role' => CircleMembership::ROLE_OWNER,
        'joined_at' => now(),
    ]);

    $this->actingAs($this->second)
        ->get(route('circles.members', $private))
        ->assertOk();

    $this->actingAs($this->member)
        ->get(route('circles.members', $private))
        ->assertForbidden();
});

test('the owner can give a member the run of the circle', function () {
    $this->actingAs($this->owner)
        ->patch(route('circles.members.role', [$this->circle, $this->second]), [
            'role' => 'owner',
        ])
        ->assertRedirect();

    expect($this->circle->memberships()->where('user_id', $this->second->id)->sole()->role)
        ->toBe(CircleMembership::ROLE_OWNER);
});

test('the owner can take the run of the circle back', function () {
    makeCoOwner($this->circle, $this->second);

    $this->actingAs($this->owner)
        ->patch(route('circles.members.role', [$this->circle, $this->second]), [
            'role' => 'member',
        ])
        ->assertRedirect();

    expect($this->circle->memberships()->where('user_id', $this->second->id)->sole()->role)
        ->toBe(CircleMembership::ROLE_MEMBER);

    $this->actingAs($this->second)
        ->get(route('circles.manage', $this->circle))
        ->assertForbidden();
});

test('a second owner can hand the rank on, being an owner outright', function () {
    makeCoOwner($this->circle, $this->second);

    $this->actingAs($this->second)
        ->patch(route('circles.members.role', [$this->circle, $this->member]), [
            'role' => 'owner',
        ])
        ->assertRedirect();

    expect($this->circle->memberships()->where('user_id', $this->member->id)->sole()->role)
        ->toBe(CircleMembership::ROLE_OWNER);
});

test('an ordinary member cannot give themselves the run of the circle', function () {
    $this->actingAs($this->member)
        ->patch(route('circles.members.role', [$this->circle, $this->member]), [
            'role' => 'owner',
        ])
        ->assertForbidden();
});

test('the founder cannot be demoted, however many owners there are', function () {
    makeCoOwner($this->circle, $this->second);

    $this->actingAs($this->second)
        ->patch(route('circles.members.role', [$this->circle, $this->owner]), [
            'role' => 'member',
        ])
        ->assertSessionHasErrors('user');

    expect($this->circle->memberships()->where('user_id', $this->owner->id)->sole()->role)
        ->toBe(CircleMembership::ROLE_OWNER);
});

test('somebody outside the circle cannot be given the run of it', function () {
    $stranger = User::factory()->create();

    $this->actingAs($this->owner)
        ->patch(route('circles.members.role', [$this->circle, $stranger]), [
            'role' => 'owner',
        ])
        ->assertSessionHasErrors('user');
});

test('a rank the circle does not hand out is turned away', function () {
    $this->actingAs($this->owner)
        ->patch(route('circles.members.role', [$this->circle, $this->second]), [
            'role' => 'moderator',
        ])
        ->assertSessionHasErrors('role');
});

test('the screens mark who runs the circle and who founded it', function () {
    makeCoOwner($this->circle, $this->second);

    foreach (['circles.manage' => $this->owner, 'circles.members' => $this->member] as $route => $viewer) {
        $this->actingAs($viewer)
            ->get(route($route, $this->circle))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('members.data.0.full_name', 'Ada Owner')
                ->where('members.data.0.is_owner', true)
                ->where('members.data.0.is_co_owner', false)
                ->where('members.data.1.full_name', 'Bea Second')
                ->where('members.data.1.is_owner', false)
                ->where('members.data.1.is_co_owner', true)
                ->where('members.data.2.is_co_owner', false)
            );
    }
});

test('a handover clears the ranks rather than adding to them', function () {
    makeCoOwner($this->circle, $this->second);

    app(TransferCircleOwnership::class)->execute($this->circle, $this->member);

    $roles = $this->circle->memberships()
        ->pluck('role', 'user_id')
        ->all();

    expect($roles[$this->member->id])->toBe(CircleMembership::ROLE_OWNER)
        ->and($roles[$this->owner->id])->toBe(CircleMembership::ROLE_MEMBER)
        // The second owner held the rank from the old owner, and the circle is
        // no longer theirs to hand out.
        ->and($roles[$this->second->id])->toBe(CircleMembership::ROLE_MEMBER)
        ->and($this->circle->fresh()->owner_id)->toBe($this->member->id);
});

test('handing a sub-circle over through the api stands the old owner down', function () {
    $inner = Circle::factory()->create([
        'owner_id' => $this->second->id,
        'parent_id' => $this->circle->id,
        'name' => 'Beginners',
    ]);

    foreach ([$this->second, $this->member] as $user) {
        CircleMembership::create([
            'user_id' => $user->id,
            'circle_id' => $inner->id,
            'role' => $user->is($this->second)
                ? CircleMembership::ROLE_OWNER
                : CircleMembership::ROLE_MEMBER,
            'joined_at' => now(),
        ]);
    }

    $this->actingAs($this->owner)
        ->putJson(route('api.v1.circles.owner.assign', [$inner, $this->member]))
        ->assertOk();

    $roles = $inner->memberships()->pluck('role', 'user_id')->all();

    expect($inner->fresh()->owner_id)->toBe($this->member->id)
        ->and($roles[$this->member->id])->toBe(CircleMembership::ROLE_OWNER)
        // The column alone would have left them still running it.
        ->and($roles[$this->second->id])->toBe(CircleMembership::ROLE_MEMBER);

    $this->actingAs($this->second)
        ->get(route('circles.manage', $inner))
        ->assertForbidden();
});

test('a second owner of the outer circle runs the circles inside it', function () {
    makeCoOwner($this->circle, $this->second);

    $inner = Circle::factory()->create([
        'owner_id' => $this->member->id,
        'parent_id' => $this->circle->id,
        'name' => 'Beginners',
    ]);

    $this->actingAs($this->second)
        ->get(route('circles.manage', $inner))
        ->assertOk();
});

test('the console counts the circles somebody was given the run of', function () {
    $theirs = Circle::factory()->create([
        'owner_id' => $this->owner->id,
        'name' => 'Evening Sitters',
    ]);

    CircleMembership::create([
        'user_id' => $this->second->id,
        'circle_id' => $theirs->id,
        'role' => CircleMembership::ROLE_OWNER,
        'joined_at' => now(),
    ]);

    $this->actingAs($this->second)
        ->get(route('dashboard.stats.my-circles'))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonFragment(['name' => 'Evening Sitters']);
});
