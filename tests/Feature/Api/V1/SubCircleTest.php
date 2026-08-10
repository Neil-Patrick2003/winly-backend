<?php

use App\Models\Circle;
use App\Models\CircleMembership;
use App\Models\Post;
use App\Models\User;
use App\Models\WinMovement;
use Laravel\Sanctum\Sanctum;

/**
 * A circle inside a circle.
 *
 * A circle inside another is open to anybody, like any circle — being in the
 * parent is not a condition of joining it. What ties the two together is the
 * reading: a win shared into the inner circle also reaches the outer one, and
 * the outer circle's owner keeps authority over the inner. One level, always.
 */
beforeEach(function () {
    $this->owner = User::factory()->create(['full_name' => 'Ada Owner']);
    $this->member = User::factory()->create(['full_name' => 'Bea Member']);
    $this->outsider = User::factory()->create(['full_name' => 'Cal Outsider']);

    $this->parent = Circle::factory()->create([
        'owner_id' => $this->owner->id,
        'name' => 'Morning Sitters',
    ]);

    foreach ([$this->owner, $this->member] as $person) {
        CircleMembership::create([
            'user_id' => $person->id,
            'circle_id' => $this->parent->id,
            'joined_at' => now(),
        ]);
    }
    $this->parent->update(['members_count' => 2]);
});

/** A circle inside the parent, owned by whoever is given. */
function subCircle(User $keeper, array $members = []): Circle
{
    $inner = Circle::factory()->create([
        'owner_id' => $keeper->id,
        'parent_id' => test()->parent->id,
        'name' => 'Beginners',
    ]);

    foreach ($members as $person) {
        CircleMembership::create([
            'user_id' => $person->id,
            'circle_id' => $inner->id,
            'joined_at' => now(),
        ]);
    }

    $inner->update(['members_count' => count($members)]);

    return $inner;
}

test('the owner can open a circle inside their own circle', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson(route('api.v1.circles.store'), [
        'name' => 'Beginners',
        'parent_id' => $this->parent->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.parent_id', $this->parent->id)
        ->assertJsonPath('data.is_sub_circle', true);
});

test('a circle cannot be opened inside somebody else circle', function () {
    Sanctum::actingAs($this->member);

    // A member, but not the owner: being let into a circle is not permission
    // to open circles inside it.
    $this->postJson(route('api.v1.circles.store'), [
        'name' => 'Beginners',
        'parent_id' => $this->parent->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('parent_id');
});

test('an inner circle cannot hold more', function () {
    $inner = subCircle($this->member, [$this->member]);

    Sanctum::actingAs($this->owner);

    $this->postJson(route('api.v1.circles.store'), [
        'name' => 'Even Newer',
        'parent_id' => $inner->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('parent_id');
});

test('anybody can join a circle inside another, parent member or not', function () {
    $inner = subCircle($this->owner, [$this->owner]);

    // Not in the parent, and it makes no difference: an inner circle is a
    // circle, and joining one is not conditional on joining anything else.
    Sanctum::actingAs($this->outsider);
    $this->postJson(route('api.v1.circles.join', $inner))->assertOk();

    Sanctum::actingAs($this->member);
    $this->postJson(route('api.v1.circles.join', $inner))->assertOk();

    expect($inner->memberships()->where('user_id', $this->outsider->id)->exists())->toBeTrue()
        ->and($inner->memberships()->where('user_id', $this->member->id)->exists())->toBeTrue();
});

test('leaving the outer circle leaves the inner ones alone', function () {
    $inner = subCircle($this->owner, [$this->owner, $this->member]);

    Sanctum::actingAs($this->member);
    $this->deleteJson(route('api.v1.circles.leave', $this->parent))->assertOk();

    // The two memberships are independent, so walking out of one is not
    // walking out of the other.
    expect($inner->memberships()->where('user_id', $this->member->id)->exists())->toBeTrue()
        ->and($inner->fresh()->members_count)->toBe(2);
});

test('the outer circle owner outranks the inner one', function () {
    $inner = subCircle($this->member, [$this->member]);

    // Renaming, which is the owner's alone — and the parent's owner is one.
    Sanctum::actingAs($this->owner);
    $this->patchJson(route('api.v1.circles.update', $inner), ['name' => 'Newcomers'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Newcomers');

    $this->deleteJson(route('api.v1.circles.destroy', $inner))->assertOk();

    expect(Circle::find($inner->id))->toBeNull();
});

test('the outer circle owner hands the inner one to a member', function () {
    $inner = subCircle($this->owner, [$this->owner, $this->member]);

    Sanctum::actingAs($this->owner);

    $this->putJson(route('api.v1.circles.owner.assign', [$inner, $this->member]))
        ->assertOk()
        ->assertJsonPath('data.id', $inner->id);

    expect($inner->fresh()->owner_id)->toBe($this->member->id);
});

test('an inner circle cannot be handed to somebody who is not in it', function () {
    $inner = subCircle($this->owner, [$this->owner]);

    Sanctum::actingAs($this->owner);

    $this->putJson(route('api.v1.circles.owner.assign', [$inner, $this->outsider]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('user');

    expect($inner->fresh()->owner_id)->toBe($this->owner->id);
});

test('a circle standing on its own cannot be handed over', function () {
    Sanctum::actingAs($this->owner);

    // Nobody has standing to reassign it: there is no circle above it.
    $this->putJson(route('api.v1.circles.owner.assign', [$this->parent, $this->member]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('circle');
});

test('a win shared into an inner circle reaches the outer one', function () {
    $inner = subCircle($this->owner, [$this->owner]);

    $post = Post::factory()->create([
        'user_id' => $this->owner->id,
        'visibility' => Post::VISIBILITY_CUSTOM,
    ]);
    WinMovement::factory()->for($post, 'post')->create();
    $inner->posts()->attach($post);

    // In the outer circle but not the inner: what is said inside carries out.
    Sanctum::actingAs($this->member);
    $this->getJson(route('api.v1.posts.show', $post))->assertOk();
    $this->getJson(route('api.v1.posts.index'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $post->id);

    // In neither: still nothing.
    Sanctum::actingAs($this->outsider);
    $this->getJson(route('api.v1.posts.show', $post))->assertForbidden();
    $this->getJson(route('api.v1.posts.index'))->assertOk()->assertJsonMissing(['id' => $post->id]);
});

test('a win shared into the outer circle does not reach the inner ones', function () {
    $inner = subCircle($this->owner, [$this->owner]);

    $post = Post::factory()->create([
        'user_id' => $this->owner->id,
        'visibility' => Post::VISIBILITY_CUSTOM,
    ]);
    WinMovement::factory()->for($post, 'post')->create();
    $this->parent->posts()->attach($post);

    // The inner circle's wall is its own. Carrying outward is not the same as
    // carrying inward, or an inner circle would be no narrowing at all.
    expect($inner->posts()->whereKey($post->getKey())->exists())->toBeFalse();
});

test('taking the outer circle down takes the inner ones with it', function () {
    $inner = subCircle($this->member, [$this->member]);

    Sanctum::actingAs($this->owner);
    $this->deleteJson(route('api.v1.circles.destroy', $this->parent))->assertOk();

    expect(Circle::find($inner->id))->toBeNull();
});

test('the circles inside one are listed to anybody who may see it', function () {
    $inner = subCircle($this->owner, [$this->owner]);

    Sanctum::actingAs($this->outsider);

    // Knowing a circle has a beginners' circle inside is not reading it.
    $this->getJson(route('api.v1.circles.sub.index', $this->parent))
        ->assertOk()
        ->assertJsonPath('data.0.id', $inner->id)
        ->assertJsonPath('data.0.is_sub_circle', true);
});

test('a private circle inside one is not listed to an outsider', function () {
    $inner = subCircle($this->owner, [$this->owner]);
    $inner->update(['is_private' => true]);

    Sanctum::actingAs($this->outsider);

    // Private means not listed, the same as in Discover and search: the name
    // alone would say the circle exists and roughly what it is for.
    $this->getJson(route('api.v1.circles.sub.index', $this->parent))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('a private circle inside one is still listed to its own members', function () {
    $inner = subCircle($this->owner, [$this->owner, $this->member]);
    $inner->update(['is_private' => true]);

    Sanctum::actingAs($this->member);

    // Hidden from strangers, not from the people already inside it.
    $this->getJson(route('api.v1.circles.sub.index', $this->parent))
        ->assertOk()
        ->assertJsonPath('data.0.id', $inner->id);
});
