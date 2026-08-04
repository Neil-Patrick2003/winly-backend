<?php

use App\Models\Circle;
use App\Models\CircleMembership;
use App\Models\Follow;
use App\Models\Post;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create(['wins_count' => 0]);
    Sanctum::actingAs($this->user);
});

test('guests are turned away', function () {
    app()['auth']->forgetGuards();

    $this->getJson(route('api.v1.discover'))->assertUnauthorized();
});

test('people are suggested by how often they post, capped at ten', function () {
    // Twelve, so the cap has something to cut.
    foreach (range(1, 12) as $rank) {
        Post::factory()->count($rank)->for(User::factory())->create();
    }

    $people = collect($this->getJson(route('api.v1.discover'))->assertOk()->json('data.people'));

    expect($people)->toHaveCount(10)
        // Busiest first.
        ->and($people->pluck('posts_count')->all())->toBe([12, 11, 10, 9, 8, 7, 6, 5, 4, 3]);
});

test('ranking counts posts, not the wins stacked on them', function () {
    // One post carrying all three pillars moves `wins_count` by three.
    $stacker = User::factory()->create(['wins_count' => 30]);
    Post::factory()->for($stacker)->create();

    $regular = User::factory()->create(['wins_count' => 2]);
    Post::factory()->count(2)->for($regular)->create();

    $people = collect($this->getJson(route('api.v1.discover'))->assertOk()->json('data.people'));

    // Two posts beats one, however many wins were bundled into it.
    expect($people->pluck('id')->all())->toBe([$regular->id, $stacker->id]);
});

test('nobody is suggested who has posted nothing, or who is already followed, or is you', function () {
    User::factory()->create(['full_name' => 'Silent Sam']);

    $followed = User::factory()->create(['full_name' => 'Already Alice']);
    Post::factory()->count(9)->for($followed)->create();
    Follow::factory()->from($this->user)->to($followed)->create();

    $stranger = User::factory()->create(['full_name' => 'New Nadia']);
    Post::factory()->for($stranger)->create();

    // The reader posts too, so only the exclusion keeps them out.
    Post::factory()->count(20)->for($this->user)->create();

    $names = collect($this->getJson(route('api.v1.discover'))->assertOk()->json('data.people'))
        ->pluck('full_name');

    expect($names->all())->toBe(['New Nadia'])
        ->and($names)->not->toContain('Silent Sam')
        ->and($names)->not->toContain('Already Alice');

    $row = collect($this->getJson(route('api.v1.discover'))->json('data.people'))
        ->firstWhere('id', $stranger->id);
    expect($row['is_following'])->toBeFalse();
});

test('the streak reported is the one still standing', function () {
    $person = User::factory()->create();
    Post::factory()->count(3)->for($person)->create();
    $person->forceFill(['streak_days' => 7, 'last_win_on' => now()->subWeek()])->save();

    $row = collect($this->getJson(route('api.v1.discover'))->assertOk()->json('data.people'))
        ->firstWhere('id', $person->id);

    // Nothing since last week, so the run is over whatever the column says.
    expect($row['streak_days'])->toBe(0);
});

test('circles come back biggest first, with where the reader stands on each', function () {
    $big = Circle::factory()->create(['name' => 'Big One', 'members_count' => 500]);
    $small = Circle::factory()->create(['name' => 'Small One', 'members_count' => 3]);
    CircleMembership::factory()->create(['user_id' => $this->user->id, 'circle_id' => $small->id]);

    $circles = collect($this->getJson(route('api.v1.discover'))->assertOk()->json('data.circles'));

    expect($circles->pluck('id')->all())->toBe([$big->id, $small->id])
        ->and($circles->firstWhere('id', $small->id)['is_member'])->toBeTrue()
        ->and($circles->firstWhere('id', $big->id)['is_member'])->toBeFalse();
});

test('the tag chips list every tag in use', function () {
    Circle::factory()->create(['tag' => 'fitness']);
    Circle::factory()->create(['tag' => 'reading']);
    Circle::factory()->create(['tag' => 'fitness']);
    Circle::factory()->create(['tag' => null]);

    $this->getJson(route('api.v1.discover'))
        ->assertOk()
        ->assertJsonPath('data.tags', ['fitness', 'reading']);
});

test('a tag narrows the circles and leaves the people alone', function () {
    Circle::factory()->create(['name' => 'Runners', 'tag' => 'fitness']);
    Circle::factory()->create(['name' => 'Readers', 'tag' => 'reading']);
    Post::factory()->for(User::factory())->create();

    $response = $this->getJson(route('api.v1.discover', ['tag' => 'fitness']))->assertOk();

    expect(collect($response->json('data.circles'))->pluck('name')->all())->toBe(['Runners'])
        ->and($response->json('data.people'))->toHaveCount(1);
});

test('a search term reaches both lists', function () {
    Circle::factory()->create(['name' => 'Sunrise Runners']);
    Circle::factory()->create(['name' => 'Book Nook']);
    Post::factory()->for(User::factory()->create(['full_name' => 'Sunrise Sally']))->create();
    Post::factory()->for(User::factory()->create(['full_name' => 'Other Person']))->create();

    $response = $this->getJson(route('api.v1.discover', ['q' => 'sunrise']))->assertOk();

    expect(collect($response->json('data.circles'))->pluck('name')->all())->toBe(['Sunrise Runners'])
        ->and(collect($response->json('data.people'))->pluck('full_name')->all())
        ->toBe(['Sunrise Sally']);
});

/*
 * Suggesting and searching are different questions.
 *
 * An empty account is not worth proposing to somebody browsing, so it stays out
 * of the suggestions. Typing a name is not browsing: it is looking for one
 * person, and answering "no results" because they have not posted yet is
 * answering a question nobody asked — least of all for a friend who has only
 * just joined, which is exactly when you go looking for them.
 */
test('searching finds somebody who has not posted yet', function () {
    $quiet = User::factory()->create(['full_name' => 'Silent Sam', 'username' => 'silent_sam']);

    $names = collect(
        $this->getJson(route('api.v1.discover', ['q' => 'silent']))->assertOk()->json('data.people')
    )->pluck('full_name');

    expect($names->all())->toBe(['Silent Sam']);

    // And the row is honest about it rather than hiding the count.
    $row = collect($this->getJson(route('api.v1.discover', ['q' => 'silent']))->json('data.people'))
        ->firstWhere('id', $quiet->id);

    expect($row['posts_count'])->toBe(0);
});

test('searching by username finds them too', function () {
    User::factory()->create(['full_name' => 'Quiet Quinn', 'username' => 'newjoiner']);

    $names = collect(
        $this->getJson(route('api.v1.discover', ['q' => 'newjoin']))->assertOk()->json('data.people')
    )->pluck('full_name');

    expect($names->all())->toBe(['Quiet Quinn']);
});

test('browsing still leaves the empty accounts out', function () {
    User::factory()->create(['full_name' => 'Silent Sam']);
    Post::factory()->for(User::factory()->create(['full_name' => 'Busy Bea']))->create();

    // No term: this is the shop window, and an account with nothing on it is
    // worse than a shorter list.
    $names = collect($this->getJson(route('api.v1.discover'))->assertOk()->json('data.people'))
        ->pluck('full_name');

    expect($names->all())->toBe(['Busy Bea']);
});
