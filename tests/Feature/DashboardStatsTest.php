<?php

use App\Models\Circle;
use App\Models\CircleBlock;
use App\Models\CircleInvitation;
use App\Models\CircleMembership;
use App\Models\Post;
use App\Models\User;
use App\Models\WinLearning;
use App\Models\WinMeditation;
use App\Models\WinMovement;

/**
 * An owner with one circle, plus a stranger's circle that nothing on the
 * console should ever count.
 */
beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->circle = Circle::factory()->create([
        'owner_id' => $this->owner->id,
        'created_at' => now()->subDay(),
    ]);

    $this->strangerCircle = Circle::factory()->create(['created_at' => now()->subDay()]);
});

/**
 * Put a post on a circle's wall.
 */
function shareInto(Circle $circle, array $attributes = []): Post
{
    $post = Post::factory()->create($attributes);
    $post->circles()->attach($circle);

    return $post;
}

/**
 * Every console endpoint, so sign-in is asserted once per route rather than
 * once in total.
 *
 * @return list<string>
 */
function consoleRoutes(): array
{
    return [
        route('dashboard'),
        route('dashboard.stats.circles'),
        route('dashboard.stats.members'),
        route('dashboard.stats.posts'),
        route('dashboard.stats.engagement'),
        route('dashboard.stats.overview'),
        route('dashboard.stats.member-overview'),
        route('dashboard.stats.my-circles'),
        route('dashboard.stats.streak-leaders'),
        route('dashboard.stats.activity'),
    ];
}

test('the console is closed to guests', function () {
    foreach (consoleRoutes() as $url) {
        $this->get($url)->assertRedirect(route('login'));
    }
});

test('circles count only the ones this person owns', function () {
    Circle::factory()->create(['owner_id' => $this->owner->id, 'created_at' => now()->subDays(9)]);

    $this->actingAs($this->owner)
        ->getJson(route('dashboard.stats.circles', ['days' => 7]))
        ->assertOk()
        ->assertJsonPath('value', 2)
        ->assertJsonPath('started', 1)
        ->assertJsonPath('previous', 1);
});

test('members count seats in the owner circles only', function () {
    $person = User::factory()->create();

    CircleMembership::create([
        'user_id' => $person->id,
        'circle_id' => $this->circle->id,
        'joined_at' => now()->subDay(),
    ]);
    CircleMembership::create([
        'user_id' => $person->id,
        'circle_id' => $this->strangerCircle->id,
        'joined_at' => now()->subDay(),
    ]);

    $this->actingAs($this->owner)
        ->getJson(route('dashboard.stats.members', ['days' => 7]))
        ->assertOk()
        ->assertJsonPath('value', 1)
        ->assertJsonPath('people', 1)
        ->assertJsonPath('joined', 1);
});

test('posts per day ignores posts outside the owner circles', function () {
    for ($i = 0; $i < 14; $i++) {
        shareInto($this->circle, ['created_at' => now()->subDay()]);
    }

    shareInto($this->strangerCircle, ['created_at' => now()->subDay()]);
    Post::factory()->create(['created_at' => now()->subDay()]);

    $this->actingAs($this->owner)
        ->getJson(route('dashboard.stats.posts', ['days' => 7]))
        ->assertOk()
        ->assertJsonPath('value', 2)
        ->assertJsonPath('total', 14);
});

test('a post shared into two owned circles is counted once', function () {
    $second = Circle::factory()->create(['owner_id' => $this->owner->id]);

    $post = shareInto($this->circle, ['created_at' => now()->subDay()]);
    $post->circles()->attach($second);

    $this->actingAs($this->owner)
        ->getJson(route('dashboard.stats.posts', ['days' => 7]))
        ->assertOk()
        ->assertJsonPath('total', 1);
});

test('engagement is the share of owned posts that drew a reply', function () {
    shareInto($this->circle, ['created_at' => now()->subDay(), 'likes_count' => 2]);
    shareInto($this->circle, ['created_at' => now()->subDay(), 'likes_count' => 1]);
    shareInto($this->circle, ['created_at' => now()->subDay(), 'comments_count' => 4]);
    shareInto($this->circle, ['created_at' => now()->subDay()]);

    shareInto($this->strangerCircle, ['created_at' => now()->subDay()]);

    $this->actingAs($this->owner)
        ->getJson(route('dashboard.stats.engagement', ['days' => 7]))
        ->assertOk()
        ->assertJsonPath('value', 75)
        ->assertJsonPath('engaged', 3)
        ->assertJsonPath('total', 4);
});

test('the overview fills every day and stays inside the owner circles', function () {
    $mine = shareInto($this->circle);
    WinMeditation::factory()->create(['post_id' => $mine->id, 'completed_at' => now()->subDay()]);
    WinLearning::factory()->create(['post_id' => $mine->id, 'completed_at' => now()->subDay()]);

    $alsoMine = shareInto($this->circle);
    WinMovement::factory()->create(['post_id' => $alsoMine->id, 'completed_at' => now()]);

    $theirs = shareInto($this->strangerCircle);
    WinMeditation::factory()->create(['post_id' => $theirs->id, 'completed_at' => now()]);

    $response = $this->actingAs($this->owner)
        ->getJson(route('dashboard.stats.overview', ['days' => 5]))
        ->assertOk()
        ->assertJsonPath('series', ['meditation', 'learning', 'movement'])
        ->assertJsonCount(5, 'points');

    $points = collect($response->json('points'))->keyBy('date');

    expect($points[now()->subDay()->toDateString()])
        ->toMatchArray(['meditation' => 1, 'learning' => 1, 'movement' => 0]);

    expect($points[now()->toDateString()])
        ->toMatchArray(['meditation' => 0, 'learning' => 0, 'movement' => 1]);
});

test('my circles lists only the ones the viewer owns', function () {
    $this->circle->update(['members_count' => 5]);

    $this->actingAs($this->owner)
        ->getJson(route('dashboard.stats.my-circles'))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->circle->id)
        ->assertJsonPath('data.0.members_count', 5);
});

test('streak leaders rank members of the owner circles', function () {
    $inside = User::factory()->create([
        'full_name' => 'Long Runner',
        'streak_days' => 12,
        'last_win_on' => now()->toDateString(),
    ]);
    $alsoInside = User::factory()->create([
        'full_name' => 'Short Runner',
        'streak_days' => 3,
        'last_win_on' => now()->subDay()->toDateString(),
    ]);
    $outside = User::factory()->create(['streak_days' => 99, 'last_win_on' => now()->toDateString()]);

    foreach ([$inside, $alsoInside] as $member) {
        CircleMembership::create([
            'user_id' => $member->id,
            'circle_id' => $this->circle->id,
            'joined_at' => now()->subDay(),
        ]);
    }

    CircleMembership::create([
        'user_id' => $outside->id,
        'circle_id' => $this->strangerCircle->id,
        'joined_at' => now()->subDay(),
    ]);

    $this->actingAs($this->owner)
        ->getJson(route('dashboard.stats.streak-leaders'))
        ->assertOk()
        ->assertJsonPath('alive', 2)
        ->assertJsonPath('at_risk', 1)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.full_name', 'Long Runner')
        ->assertJsonPath('data.0.logged_today', true)
        ->assertJsonPath('data.1.logged_today', false);
});

test('the activity feed stays inside the owner circles', function () {
    $mine = shareInto($this->circle, ['caption' => 'Good morning']);
    WinMeditation::factory()->create(['post_id' => $mine->id]);
    WinMovement::factory()->create(['post_id' => $mine->id]);

    shareInto($this->strangerCircle, ['caption' => 'Not yours']);

    $this->actingAs($this->owner)
        ->getJson(route('dashboard.stats.activity'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.caption', 'Good morning')
        ->assertJsonPath('data.0.wins', ['meditation', 'movement']);
});

test('the member overview charts joins and counts every standing', function () {
    $joiner = User::factory()->create();

    CircleMembership::create([
        'user_id' => $joiner->id,
        'circle_id' => $this->circle->id,
        'joined_at' => now()->subDay(),
    ]);

    // A membership in somebody else's circle must not reach this panel.
    CircleMembership::create([
        'user_id' => $joiner->id,
        'circle_id' => $this->strangerCircle->id,
        'joined_at' => now()->subDay(),
    ]);

    CircleInvitation::factory()->create([
        'circle_id' => $this->circle->id,
        'status' => CircleInvitation::PENDING,
    ]);
    CircleInvitation::factory()->create([
        'circle_id' => $this->circle->id,
        'status' => CircleInvitation::DECLINED,
    ]);
    CircleInvitation::factory()->create([
        'circle_id' => $this->strangerCircle->id,
        'status' => CircleInvitation::PENDING,
    ]);

    CircleBlock::create([
        'circle_id' => $this->circle->id,
        'user_id' => User::factory()->create()->id,
        'blocked_by' => $this->owner->id,
    ]);

    $response = $this->actingAs($this->owner)
        ->getJson(route('dashboard.stats.member-overview', ['days' => 5]))
        ->assertOk()
        ->assertJsonCount(5, 'points')
        ->assertJsonPath('statuses.accepted', 1)
        ->assertJsonPath('statuses.pending', 1)
        ->assertJsonPath('statuses.declined', 1)
        ->assertJsonPath('statuses.blocked', 1);

    $points = collect($response->json('points'))->keyBy('date');

    expect($points[now()->subDay()->toDateString()]['joined'])->toBe(1);
    expect($points[now()->toDateString()]['joined'])->toBe(0);
});

test('an owner with no circles gets zeros rather than the whole app', function () {
    shareInto($this->strangerCircle, ['created_at' => now()->subDay(), 'likes_count' => 3]);

    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->getJson(route('dashboard.stats.circles'))
        ->assertOk()
        ->assertJsonPath('value', 0);

    $this->actingAs($stranger)
        ->getJson(route('dashboard.stats.posts'))
        ->assertOk()
        ->assertJsonPath('total', 0);

    $this->actingAs($stranger)
        ->getJson(route('dashboard.stats.activity'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
