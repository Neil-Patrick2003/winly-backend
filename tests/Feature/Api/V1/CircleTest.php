<?php

use App\Models\Circle;
use App\Models\CircleMembership;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

test('guests are turned away', function () {
    app()['auth']->forgetGuards();

    $this->getJson(route('api.v1.circles.index'))->assertUnauthorized();
    $this->postJson(route('api.v1.circles.store'), ['name' => 'Runners'])->assertUnauthorized();
});

test('a circle can be started, and whoever made it is in it', function () {
    $response = $this->postJson(route('api.v1.circles.store'), [
        'name' => 'Morning Runners',
        'description' => 'Before the sun is up.',
        'tag' => 'fitness',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Morning Runners')
        ->assertJsonPath('data.is_owner', true)
        ->assertJsonPath('data.is_member', true)
        ->assertJsonPath('data.is_private', false)
        ->assertJsonPath('data.members_count', 1)
        // Derived rather than asked for.
        ->assertJsonPath('data.icon_initial', 'M');

    expect($response->json('data.color_hex'))->toStartWith('#');
    expect(Circle::sole()->owner_id)->toBe($this->user->id);
    expect(CircleMembership::count())->toBe(1);
});

test('two circles cannot share a name', function () {
    Circle::factory()->create(['name' => 'Readers']);

    $this->postJson(route('api.v1.circles.store'), ['name' => 'Readers'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

test('a private circle is refused rather than quietly made public', function () {
    $this->postJson(route('api.v1.circles.store'), ['name' => 'Inner Ring', 'is_private' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('is_private');

    expect(Circle::count())->toBe(0);
});

test('the list carries the ones you made and the ones you joined, and nothing else', function () {
    $mine = Circle::factory()->create(['owner_id' => $this->user->id]);

    $joined = Circle::factory()->create();
    CircleMembership::factory()->create(['user_id' => $this->user->id, 'circle_id' => $joined->id]);

    Circle::factory()->create();

    $circles = collect($this->getJson(route('api.v1.circles.index'))->assertOk()->json('data'));

    expect($circles)->toHaveCount(2)
        ->and($circles->firstWhere('id', $mine->id)['is_owner'])->toBeTrue()
        ->and($circles->firstWhere('id', $joined->id)['is_owner'])->toBeFalse()
        ->and($circles->firstWhere('id', $joined->id)['is_member'])->toBeTrue();
});

test('a circle you own but left still shows as yours', function () {
    $mine = Circle::factory()->create(['owner_id' => $this->user->id]);

    $circles = collect($this->getJson(route('api.v1.circles.index'))->assertOk()->json('data'));

    expect($circles->firstWhere('id', $mine->id))
        ->toMatchArray(['is_owner' => true, 'is_member' => false]);
});

test('members are listed with the owner marked', function () {
    $circle = Circle::factory()->create(['owner_id' => $this->user->id]);
    $other = User::factory()->create(['full_name' => 'Later Larry']);

    CircleMembership::factory()->create([
        'user_id' => $this->user->id,
        'circle_id' => $circle->id,
        'joined_at' => now()->subHour(),
    ]);
    CircleMembership::factory()->create([
        'user_id' => $other->id,
        'circle_id' => $circle->id,
        'joined_at' => now(),
    ]);

    $members = collect(
        $this->getJson(route('api.v1.circles.members', $circle))->assertOk()->json('data')
    );

    // Most recently joined first.
    expect($members->pluck('id')->all())->toBe([$other->id, $this->user->id])
        ->and($members->firstWhere('id', $this->user->id)['is_owner'])->toBeTrue()
        ->and($members->firstWhere('id', $other->id)['is_owner'])->toBeFalse();
});

test('joining twice counts once, and leaving puts the count back', function () {
    $circle = Circle::factory()->create();

    $this->postJson(route('api.v1.circles.join', $circle))
        ->assertOk()
        ->assertJsonPath('data.is_member', true)
        ->assertJsonPath('data.members_count', 1);

    $this->postJson(route('api.v1.circles.join', $circle))
        ->assertOk()
        ->assertJsonPath('data.members_count', 1);

    expect(CircleMembership::count())->toBe(1);

    $this->deleteJson(route('api.v1.circles.leave', $circle))
        ->assertOk()
        ->assertJsonPath('data.is_member', false)
        ->assertJsonPath('data.members_count', 0);

    // Leaving one you are not in is treated as already done.
    $this->deleteJson(route('api.v1.circles.leave', $circle))
        ->assertOk()
        ->assertJsonPath('data.members_count', 0);
});

test('only the owner can take a circle down', function () {
    $theirs = Circle::factory()->create();

    $this->deleteJson(route('api.v1.circles.destroy', $theirs))->assertForbidden();

    $mine = Circle::factory()->create(['owner_id' => $this->user->id]);
    $this->deleteJson(route('api.v1.circles.destroy', $mine))->assertOk();

    expect(Circle::whereKey($mine->id)->exists())->toBeFalse();
});

test('an ownerless circle is nobodys to delete', function () {
    // Seeded before ownership existed.
    $orphan = Circle::factory()->create(['owner_id' => null]);

    $this->deleteJson(route('api.v1.circles.destroy', $orphan))->assertForbidden();
});
