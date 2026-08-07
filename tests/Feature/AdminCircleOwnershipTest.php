<?php

use App\Models\Circle;
use App\Models\CircleMembership;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->owner = User::factory()->create(['full_name' => 'Ada Owner']);
    $this->member = User::factory()->create(['full_name' => 'Bea Member']);
    $this->outsider = User::factory()->create(['full_name' => 'Cal Outsider']);

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
});

test('guests are sent to login', function () {
    $this->get(route('admin.circles'))->assertRedirect(route('login'));
});

test('the admin screens do not exist for anybody but staff', function () {
    // 404 rather than 403: a member who guesses the address learns nothing
    // about whether the page is there.
    $this->actingAs($this->owner)
        ->get(route('admin.circles'))
        ->assertNotFound();
});

test('staff see every circle, not only the ones they are in', function () {
    // The admin belongs to none of these.
    Circle::factory()->create(['owner_id' => $this->owner->id, 'name' => 'Evening Runners']);

    $this->actingAs($this->admin)
        ->get(route('admin.circles'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/circles')
            ->has('circles.data', 2)
            ->where('circles.data.0.name', 'Evening Runners')
            ->where('circles.data.0.owner.full_name', 'Ada Owner')
            ->where('counts.all', 2)
            ->etc()
        );
});

test('a circle nobody owns is listed first and flagged', function () {
    Circle::factory()->create(['owner_id' => null, 'name' => 'Zed Orphans']);

    $this->actingAs($this->admin)
        ->get(route('admin.circles'))
        ->assertOk()
        // Ahead of "Morning Sitters" despite the name: it is the stuck one.
        ->assertInertia(fn ($page) => $page
            ->where('circles.data.0.name', 'Zed Orphans')
            ->where('circles.data.0.owner', null)
            ->where('counts.ownerless', 1)
            ->etc()
        );
});

test('circles can be filtered down to the ownerless ones', function () {
    Circle::factory()->create(['owner_id' => null, 'name' => 'Zed Orphans']);

    $this->actingAs($this->admin)
        ->get(route('admin.circles', ['filter' => ['state' => 'ownerless']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('circles.data', 1)
            ->where('circles.data.0.name', 'Zed Orphans')
            ->etc()
        );
});

test('circles can be searched by name', function () {
    Circle::factory()->create(['owner_id' => $this->owner->id, 'name' => 'Evening Runners']);

    $this->actingAs($this->admin)
        ->get(route('admin.circles', ['filter' => ['search' => 'Evening']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('circles.data', 1)
            ->where('circles.data.0.name', 'Evening Runners')
            ->etc()
        );
});

test('staff can open any circle members, posts, tracker and manage tab', function () {
    /*
     * The admin is in none of these circles. Reaching them is the whole point:
     * the policy lets staff through, so the ordinary screens serve them and
     * none of these had to be rebuilt behind the admin prefix.
     */
    foreach (['circles.members', 'circles.posts', 'circles.tracker', 'circles.manage'] as $route) {
        $this->actingAs($this->admin)
            ->get(route($route, $this->circle))
            ->assertOk();
    }
});

test('a member still cannot manage a circle they do not own', function () {
    // The staff bypass must not have loosened anything for everybody else.
    $this->actingAs($this->member)
        ->get(route('circles.manage', $this->circle))
        ->assertForbidden();
});

test('the transfer control is offered to staff and withheld from the owner', function () {
    $this->actingAs($this->admin)
        ->get(route('circles.manage', $this->circle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('circle.can_transfer_ownership', true)
            ->where('circle.owner.full_name', 'Ada Owner')
            ->etc()
        );

    // An owner giving their own circle away is a different feature.
    $this->actingAs($this->owner)
        ->get(route('circles.manage', $this->circle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('circle.can_transfer_ownership', false)
            ->etc()
        );
});

test('staff can start a circle owned by somebody else', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.circles.store'), [
            'name' => 'Evening Runners',
            'description' => 'For the after-work crowd.',
            'tag' => 'running',
            'owner_id' => $this->member->id,
        ])
        ->assertRedirect();

    $circle = Circle::where('name', 'Evening Runners')->first();

    // Owned by the person named, not by whoever happened to be signed in.
    expect($circle->owner_id)->toBe($this->member->id);
    expect($circle->owner_id)->not->toBe($this->admin->id);

    // And they are in it, exactly as if they had made it themselves.
    expect($circle->memberships()->where('user_id', $this->member->id)->exists())
        ->toBeTrue();
    expect($circle->members_count)->toBe(1);
});

test('a circle cannot be started without saying who owns it', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.circles.store'), ['name' => 'Nobody Home'])
        ->assertSessionHasErrors('owner_id');

    expect(Circle::where('name', 'Nobody Home')->exists())->toBeFalse();
});

test('a circle cannot be started for somebody who has left', function () {
    $this->outsider->delete();

    $this->actingAs($this->admin)
        ->post(route('admin.circles.store'), [
            'name' => 'Ghost Circle',
            'owner_id' => $this->outsider->id,
        ])
        ->assertSessionHasErrors('owner_id');

    expect(Circle::where('name', 'Ghost Circle')->exists())->toBeFalse();
});

test('the name rules of the ordinary create form still apply', function () {
    // Same request object as the app's own endpoint, so a duplicate name is
    // refused here too rather than making a circle nobody can tell apart.
    $this->actingAs($this->admin)
        ->post(route('admin.circles.store'), [
            'name' => 'Morning Sitters',
            'owner_id' => $this->member->id,
        ])
        ->assertSessionHasErrors('name');
});

test('a member cannot start a circle through the admin route', function () {
    $this->actingAs($this->member)
        ->post(route('admin.circles.store'), [
            'name' => 'Sneaky Circle',
            'owner_id' => $this->member->id,
        ])
        ->assertNotFound();

    expect(Circle::where('name', 'Sneaky Circle')->exists())->toBeFalse();
});

test('the owner picker can be searched', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.circles', ['owner_search' => 'Bea']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('ownerCandidates', 1)
            ->where('ownerCandidates.0.full_name', 'Bea Member')
            ->etc()
        );
});

test('staff can hand a circle to one of its members', function () {
    $this->actingAs($this->admin)
        ->patch(route('admin.circles.owner', $this->circle), [
            'owner_id' => $this->member->id,
        ])
        ->assertRedirect();

    expect($this->circle->refresh()->owner_id)->toBe($this->member->id);
});

test('handing it over leaves the previous owner in the circle', function () {
    $this->actingAs($this->admin)
        ->patch(route('admin.circles.owner', $this->circle), [
            'owner_id' => $this->member->id,
        ]);

    // Giving up the running of a group is not the same as leaving it.
    expect($this->circle->memberships()->where('user_id', $this->owner->id)->exists())
        ->toBeTrue();
    expect($this->circle->memberships()->count())->toBe(2);
});

test('the new owner can manage the circle and the old one cannot', function () {
    $this->actingAs($this->admin)
        ->patch(route('admin.circles.owner', $this->circle), [
            'owner_id' => $this->member->id,
        ]);

    $this->actingAs($this->member)
        ->get(route('circles.manage', $this->circle))
        ->assertOk();

    $this->actingAs($this->owner)
        ->get(route('circles.manage', $this->circle))
        ->assertForbidden();
});

test('a circle cannot be handed to somebody who is not in it', function () {
    $this->actingAs($this->admin)
        ->patch(route('admin.circles.owner', $this->circle), [
            'owner_id' => $this->outsider->id,
        ])
        ->assertSessionHasErrors('owner_id');

    expect($this->circle->refresh()->owner_id)->toBe($this->owner->id);
});

test('an owner cannot hand their own circle on', function () {
    $this->actingAs($this->owner)
        ->patch(route('admin.circles.owner', $this->circle), [
            'owner_id' => $this->member->id,
        ])
        ->assertNotFound();

    expect($this->circle->refresh()->owner_id)->toBe($this->owner->id);
});

test('a circle nobody owns can be given one', function () {
    $orphan = Circle::factory()->create(['owner_id' => null, 'name' => 'Zed Orphans']);

    CircleMembership::create([
        'user_id' => $this->member->id,
        'circle_id' => $orphan->id,
        'joined_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->patch(route('admin.circles.owner', $orphan), [
            'owner_id' => $this->member->id,
        ]);

    expect($orphan->refresh()->owner_id)->toBe($this->member->id);
});
