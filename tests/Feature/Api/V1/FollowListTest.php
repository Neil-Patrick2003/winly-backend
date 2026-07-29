<?php

use App\Models\Follow;
use App\Models\Post;
use App\Models\Story;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

test('guests cannot read follow lists', function () {
    app()['auth']->forgetGuards();

    $this->getJson(route('api.v1.users.following', $this->user))->assertUnauthorized();
    $this->getJson(route('api.v1.users.followers', $this->user))->assertUnauthorized();
});

test('following lists the people a user follows, newest first', function () {
    $alice = User::factory()->create(['full_name' => 'Alice']);
    $bob = User::factory()->create(['full_name' => 'Bob']);

    Follow::factory()->from($this->user)->to($alice)->create(['created_at' => now()->subHour()]);
    Follow::factory()->from($this->user)->to($bob)->create(['created_at' => now()]);

    $this->getJson(route('api.v1.users.following', $this->user))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.full_name', 'Bob')
        ->assertJsonPath('data.1.full_name', 'Alice')
        ->assertJsonStructure(['data' => [['id', 'full_name', 'username', 'avatar_url']]])
        ->assertJsonMissingPath('data.0.email');
});

test('followers lists the people who follow a user', function () {
    $fan = User::factory()->create(['full_name' => 'Fan']);
    Follow::factory()->from($fan)->to($this->user)->create();

    $this->getJson(route('api.v1.users.followers', $this->user))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.full_name', 'Fan');
});

test('the two lists do not bleed into each other', function () {
    $followee = User::factory()->create(['full_name' => 'Followee']);
    $follower = User::factory()->create(['full_name' => 'Follower']);

    Follow::factory()->from($this->user)->to($followee)->create();
    Follow::factory()->from($follower)->to($this->user)->create();

    $this->getJson(route('api.v1.users.following', $this->user))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.full_name', 'Followee');

    $this->getJson(route('api.v1.users.followers', $this->user))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.full_name', 'Follower');
});

test('each row says whether the caller follows that person', function () {
    $subject = User::factory()->create();
    $mutual = User::factory()->create(['full_name' => 'Mutual']);
    $stranger = User::factory()->create(['full_name' => 'Stranger']);

    Follow::factory()->from($subject)->to($mutual)->create(['created_at' => now()]);
    Follow::factory()->from($subject)->to($stranger)->create(['created_at' => now()->subHour()]);
    Follow::factory()->from($this->user)->to($mutual)->create();

    $rows = collect(
        $this->getJson(route('api.v1.users.following', $subject))->assertOk()->json('data')
    )->keyBy('full_name');

    expect($rows['Mutual']['is_following'])->toBeTrue();
    expect($rows['Stranger']['is_following'])->toBeFalse();
});

test('an empty list is not an error', function () {
    $this->getJson(route('api.v1.users.following', $this->user))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('the list is cursor paginated without repeating people', function () {
    $subject = User::factory()->create();

    foreach (range(1, 5) as $i) {
        Follow::factory()->from($subject)->to(User::factory()->create())
            ->create(['created_at' => now()->subMinutes($i)]);
    }

    $first = $this->getJson(route('api.v1.users.following', ['user' => $subject, 'per_page' => 2]))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $cursor = $first->json('meta.next_cursor');
    expect($cursor)->not->toBeNull();

    $second = $this->getJson(route('api.v1.users.following', ['user' => $subject, 'per_page' => 2, 'cursor' => $cursor]))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect(collect($first->json('data'))->pluck('id')
        ->intersect(collect($second->json('data'))->pluck('id')))->toBeEmpty();
});

test('the page size is capped', function () {
    $this->getJson(route('api.v1.users.following', ['user' => $this->user, 'per_page' => 500]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');
});

test('an unknown user is a 404', function () {
    $this->getJson(route('api.v1.users.following', '019fa2bf-0000-7000-8000-000000000000'))
        ->assertNotFound();
});

test('rows flag whoever has a story still running', function () {
    $subject = User::factory()->create();
    $storyteller = User::factory()->create(['full_name' => 'Storyteller']);
    $quiet = User::factory()->create(['full_name' => 'Quiet']);

    Story::factory()->create(['user_id' => $storyteller->id]);
    Story::factory()->expired()->create(['user_id' => $quiet->id]);

    Follow::factory()->from($subject)->to($storyteller)->create(['created_at' => now()]);
    Follow::factory()->from($subject)->to($quiet)->create(['created_at' => now()->subHour()]);

    $rows = collect(
        $this->getJson(route('api.v1.users.following', $subject))->assertOk()->json('data')
    )->keyBy('full_name');

    expect($rows['Storyteller']['has_active_story'])->toBeTrue();
    expect($rows['Quiet']['has_active_story'])->toBeFalse();
});

test('listing costs the same however many people are on it', function () {
    $subject = User::factory()->create();

    $measure = function () use ($subject): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson(route('api.v1.users.following', ['user' => $subject, 'per_page' => 50]))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    foreach (range(1, 2) as $i) {
        Follow::factory()->from($subject)->to(User::factory()->create())->create();
    }
    $few = $measure();

    foreach (range(1, 20) as $i) {
        Follow::factory()->from($subject)->to(User::factory()->create())->create();
    }

    expect($measure())->toBe($few);
});

test('the feed flags authors who have a story running', function () {
    $teller = User::factory()->create();
    Story::factory()->create(['user_id' => $teller->id]);
    Post::factory()->by($teller)->create();

    $quiet = User::factory()->create();
    Story::factory()->expired()->create(['user_id' => $quiet->id]);
    Post::factory()->by($quiet)->create();

    $rows = collect($this->getJson(route('api.v1.posts.index'))->assertOk()->json('data'))
        ->keyBy('author.id');

    expect($rows[$teller->id]['author']['has_active_story'])->toBeTrue();
    expect($rows[$quiet->id]['author']['has_active_story'])->toBeFalse();
});
