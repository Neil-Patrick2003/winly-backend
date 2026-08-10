<?php

use App\Models\Circle;
use App\Models\Post;
use App\Models\User;
use App\Models\WinMeditation;
use App\Models\WinMovement;
use App\Support\Day;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
});

test('the stat endpoints do not exist for anybody but staff', function () {
    $member = User::factory()->create();

    $stats = [
        'signups', 'active', 'posts', 'streaks', 'accounts', 'circles',
        'wins', 'engagement', 'win-mix', 'signups-series', 'posts-series',
    ];

    foreach ($stats as $stat) {
        $this->actingAs($member)
            ->getJson(route("admin.stats.{$stat}"))
            ->assertNotFound();
    }
});

test('the dashboard itself is staff only', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.dashboard'))
        ->assertNotFound();

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/dashboard'));
});

test('signups count accounts opened in the window, against the one before', function () {
    // Two more this week plus the admin from beforeEach, one the week before.
    User::factory()->count(2)->create(['created_at' => now()->subDays(2)]);
    User::factory()->create(['created_at' => now()->subDays(10)]);

    $this->actingAs($this->admin)
        ->getJson(route('admin.stats.signups', ['days' => 7]))
        ->assertOk()
        ->assertJson(['value' => 3, 'previous' => 1]);
});

test('signups still count somebody who has since closed their account', function () {
    // Signing up is a thing that happened; deleting the account later does not
    // rewrite the day it happened on.
    User::factory()->create(['created_at' => now()->subDay()])->delete();

    $this->actingAs($this->admin)
        ->getJson(route('admin.stats.signups', ['days' => 7]))
        ->assertOk()
        ->assertJson(['value' => 2]);
});

test('active accounts count people seen in the window, once each', function () {
    User::factory()->count(2)->create(['last_active_at' => now()->subDay()]);
    User::factory()->create(['last_active_at' => now()->subDays(40)]);

    $this->actingAs($this->admin)
        ->getJson(route('admin.stats.active', ['days' => 7]))
        ->assertOk()
        // The admin's own `last_active_at` is now, so it counts too.
        ->assertJson(['value' => 3, 'total' => 4]);
});

test('active accounts offer no change figure', function () {
    /*
     * `last_active_at` holds only the most recent visit, so the previous window
     * cannot be recovered — it can only ever look emptier the longer ago it
     * was, which would read as growth every single time.
     */
    $this->actingAs($this->admin)
        ->getJson(route('admin.stats.active', ['days' => 7]))
        ->assertOk()
        ->assertJson(['change' => null]);
});

test('posts per day is an average over the window', function () {
    $author = User::factory()->create();

    Post::factory()->count(7)->create([
        'user_id' => $author->id,
        'created_at' => now()->subDay(),
    ]);

    $this->actingAs($this->admin)
        ->getJson(route('admin.stats.posts', ['days' => 7]))
        ->assertOk()
        ->assertJson(['value' => 1.0, 'total' => 7]);
});

test('streaks running counts only the ones still standing', function () {
    // Won yesterday: still going.
    User::factory()->create([
        'last_win_on' => today()->subDay(),
        'streak_days' => 4,
        'longest_streak' => 4,
    ]);

    // Won a fortnight ago with the columns left where they were — exactly what
    // reading `streak_days` alone would miscount as a live streak. Their best
    // run still stands as a record, which is a different question.
    User::factory()->create([
        'last_win_on' => today()->subDays(14),
        'streak_days' => 9,
        'longest_streak' => 9,
    ]);

    $this->actingAs($this->admin)
        ->getJson(route('admin.stats.streaks'))
        ->assertOk()
        ->assertJson(['value' => 1, 'longest' => 9]);
});

test('the win mix counts a person once per pillar per day', function () {
    $author = User::factory()->create();
    $circle = Circle::factory()->create(['owner_id' => $author->id]);

    // Two sittings and a walk, all today. The day is worth one meditation and
    // one movement, the same as everywhere else in the app.
    foreach ([1, 2] as $ignored) {
        WinMeditation::factory()->create([
            'post_id' => postInCircle($circle, $author)->id,
            'completed_at' => now(),
        ]);
    }

    WinMovement::factory()->create([
        'post_id' => postInCircle($circle, $author)->id,
        'completed_at' => now(),
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson(route('admin.stats.win-mix', ['days' => 7]))
        ->assertOk();

    $today = collect($response->json('points'))
        ->firstWhere('date', Day::dateOf(now()));

    expect($today['meditation'])->toBe(1);
    expect($today['movement'])->toBe(1);
    expect($today['learning'])->toBe(0);
});

test('the win mix and growth series fill in the quiet days', function () {
    // A line drawn straight between two populated days would imply activity on
    // the ones between them.
    foreach (['win-mix', 'signups-series', 'posts-series'] as $stat) {
        $response = $this->actingAs($this->admin)
            ->getJson(route("admin.stats.{$stat}", ['days' => 7]))
            ->assertOk();

        expect($response->json('points'))->toHaveCount(7);
    }
});

test('signups and posts are separate endpoints, not one payload', function () {
    $author = User::factory()->create(['created_at' => now()]);

    Post::factory()->count(3)->create([
        'user_id' => $author->id,
        'created_at' => now(),
    ]);

    // The local date the seeded rows fall on. `today()` is the UTC one, which
    // is a different day for the eight hours either side of local midnight.
    $today = Day::dateOf(now());

    /*
     * Kept apart rather than summed onto one axis or one response: they are
     * different scales, drawn as two plots, and one slow query should not hold
     * up the other's chart.
     */
    $posts = $this->actingAs($this->admin)
        ->getJson(route('admin.stats.posts-series', ['days' => 7]))
        ->assertOk();

    $signups = $this->actingAs($this->admin)
        ->getJson(route('admin.stats.signups-series', ['days' => 7]))
        ->assertOk();

    expect(collect($posts->json('points'))->firstWhere('date', $today)['value'])->toBe(3);
    expect(collect($signups->json('points'))->firstWhere('date', $today)['value'])
        ->toBeGreaterThanOrEqual(1);
});

test('accounts reports the total and the ones locked out', function () {
    User::factory()->create(['email_verified_at' => null]);
    User::factory()->create()->delete();

    $this->actingAs($this->admin)
        ->getJson(route('admin.stats.accounts'))
        ->assertOk()
        // The closed one is out of the total but counted on its own row.
        ->assertJson(['value' => 2, 'unverified' => 1, 'closed' => 1, 'admins' => 1]);
});

test('circles are split into buckets that do not overlap', function () {
    Circle::factory()->create(['owner_id' => $this->admin->id]);
    Circle::factory()->count(2)->create(['owner_id' => null]);

    $response = $this->actingAs($this->admin)
        ->getJson(route('admin.stats.circles'))
        ->assertOk()
        /*
         * The ownerless pair is not counted again under "quiet", even though
         * neither has been posted in. The donut draws these as parts of one
         * whole, and slices that double-count would sum past the total.
         */
        ->assertJson(['value' => 3, 'ownerless' => 2, 'quiet' => 1, 'active' => 0]);

    $body = $response->json();

    expect($body['active'] + $body['quiet'] + $body['ownerless'])
        ->toBe($body['value']);
});

test('wins logged counts a person once per pillar per day', function () {
    $author = User::factory()->create();
    $circle = Circle::factory()->create(['owner_id' => $author->id]);

    // Two sittings today is one meditation; the walk adds a second win.
    foreach ([1, 2] as $ignored) {
        WinMeditation::factory()->create([
            'post_id' => postInCircle($circle, $author)->id,
            'completed_at' => now(),
        ]);
    }

    WinMovement::factory()->create([
        'post_id' => postInCircle($circle, $author)->id,
        'completed_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->getJson(route('admin.stats.wins', ['days' => 7]))
        ->assertOk()
        ->assertJson(['value' => 2]);
});

test('engagement is the share of posts that drew something', function () {
    $author = User::factory()->create();

    Post::factory()->create([
        'user_id' => $author->id,
        'created_at' => now(),
        'likes_count' => 2,
    ]);

    Post::factory()->count(3)->create([
        'user_id' => $author->id,
        'created_at' => now(),
        'likes_count' => 0,
        'comments_count' => 0,
    ]);

    // A rate bounded at 100, not interactions per post: one in four landed.
    $this->actingAs($this->admin)
        ->getJson(route('admin.stats.engagement', ['days' => 7]))
        ->assertOk()
        ->assertJson(['value' => 25.0, 'engaged' => 1, 'total' => 4]);
});

test('a win just after local midnight lands on the new day, not the UTC one', function () {
    // The bucket boundary. In UTC+8 this instant is still the previous UTC
    // date, so a chart grouped on `DATE(completed_at)` drew it a day early.
    $this->travelTo(atLocal('2026-07-29 00:30:00'));

    $circle = Circle::factory()->create();
    $author = User::factory()->create();

    WinMovement::factory()->create([
        'post_id' => postInCircle($circle, $author)->id,
        'completed_at' => now(),
    ]);

    $points = collect(
        $this->actingAs($this->admin)
            ->getJson(route('admin.stats.win-mix', ['days' => 7]))
            ->assertOk()
            ->json('points')
    )->keyBy('date');

    expect($points['2026-07-29']['movement'])->toBe(1);
    expect($points['2026-07-28']['movement'])->toBe(0);
});
