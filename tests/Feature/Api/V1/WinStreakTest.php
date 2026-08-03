<?php

use App\Models\User;
use App\Models\WinMeditation;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

/**
 * Post a movement win, the cheapest kind to set up.
 */
function postAWin(): void
{
    test()->postJson(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [['type' => 'movement', 'movement_type' => 'run']],
    ])->assertCreated();
}

test('the first win of all starts the streak at one', function () {
    postAWin();

    $this->user->refresh();

    expect($this->user->streak_days)->toBe(1);
    expect($this->user->longest_streak)->toBe(1);
    expect($this->user->last_win_on->toDateString())->toBe(today()->toDateString());
});

test('a second post the same day does not move the streak', function () {
    $this->travelTo(Carbon::parse('2026-07-28 08:00:00'));
    postAWin();

    $this->travelTo(Carbon::parse('2026-07-28 20:00:00'));
    postAWin();

    expect($this->user->refresh()->streak_days)->toBe(1);
});

test('three posts in one day still count as one day', function () {
    $this->travelTo(Carbon::parse('2026-07-28 06:00:00'));
    postAWin();

    $this->travelTo(Carbon::parse('2026-07-28 13:00:00'));
    postAWin();

    $this->travelTo(Carbon::parse('2026-07-28 23:30:00'));
    postAWin();

    $this->user->refresh();

    expect($this->user->streak_days)->toBe(1);
    expect($this->user->longest_streak)->toBe(1);
    // One day of movement, however many times it was posted.
    expect($this->user->wins_count)->toBe(1);
});

test('a win the next day carries the streak forward', function () {
    $this->travelTo(Carbon::parse('2026-07-28 09:00:00'));
    postAWin();

    $this->travelTo(Carbon::parse('2026-07-29 09:00:00'));
    postAWin();

    $this->travelTo(Carbon::parse('2026-07-30 09:00:00'));
    postAWin();

    $this->user->refresh();

    expect($this->user->streak_days)->toBe(3);
    expect($this->user->longest_streak)->toBe(3);
});

test('the day rolls over even when the posts are minutes apart', function () {
    $this->travelTo(Carbon::parse('2026-07-28 23:58:00'));
    postAWin();

    $this->travelTo(Carbon::parse('2026-07-29 00:02:00'));
    postAWin();

    expect($this->user->refresh()->streak_days)->toBe(2);
});

test('a missed day starts the streak over', function () {
    $this->travelTo(Carbon::parse('2026-07-28 09:00:00'));
    postAWin();

    $this->travelTo(Carbon::parse('2026-07-29 09:00:00'));
    postAWin();

    $this->travelTo(Carbon::parse('2026-07-31 09:00:00'));
    postAWin();

    expect($this->user->refresh()->streak_days)->toBe(1);
});

test('the longest streak survives a broken one', function () {
    foreach (['2026-07-20', '2026-07-21', '2026-07-22', '2026-07-23'] as $day) {
        $this->travelTo(Carbon::parse("{$day} 09:00:00"));
        postAWin();
    }

    expect($this->user->refresh()->longest_streak)->toBe(4);

    $this->travelTo(Carbon::parse('2026-07-28 09:00:00'));
    postAWin();

    $this->user->refresh();

    expect($this->user->streak_days)->toBe(1);
    expect($this->user->longest_streak)->toBe(4);
});

test('a longer streak raises the longest one as it goes', function () {
    foreach (['2026-07-26', '2026-07-27', '2026-07-28'] as $day) {
        $this->travelTo(Carbon::parse("{$day} 09:00:00"));
        postAWin();
    }

    $this->user->refresh();

    expect($this->user->streak_days)->toBe(3);
    expect($this->user->longest_streak)->toBe(3);
});

test('one persons streak does not touch anybody elses', function () {
    $other = User::factory()->create();

    $this->travelTo(Carbon::parse('2026-07-28 09:00:00'));
    postAWin();

    expect($other->refresh()->streak_days)->toBe(0);
    expect($other->last_win_on)->toBeNull();
});

test('the three pillars are counted apart on the same day', function () {
    $this->travelTo(Carbon::parse('2026-07-28 07:00:00'));

    $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [
            ['type' => 'meditation', 'duration_minutes' => 10],
            ['type' => 'movement', 'movement_type' => 'walk'],
        ],
    ])->assertCreated();

    // A second sitting the same evening: shared, and not counted again.
    $this->travelTo(Carbon::parse('2026-07-28 21:00:00'));

    $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [['type' => 'meditation', 'duration_minutes' => 20]],
    ])->assertCreated();

    expect($this->user->refresh()->wins_count)->toBe(2);
    expect(WinMeditation::count())->toBe(2);
});

test('the same pillar on two days counts on both of them', function () {
    $this->travelTo(Carbon::parse('2026-07-28 09:00:00'));
    postAWin();

    $this->travelTo(Carbon::parse('2026-07-29 09:00:00'));
    postAWin();

    expect($this->user->refresh()->wins_count)->toBe(2);
});

test('a win backdated onto a day already logged does not count again', function () {
    $this->travelTo(Carbon::parse('2026-07-29 09:00:00'));
    postAWin();

    $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [[
            'type' => 'movement',
            'movement_type' => 'walk',
            // Yesterday's walk, written up this morning.
            'completed_at' => '2026-07-28 18:00:00',
        ]],
    ])->assertCreated();

    // Two days walked, so two — the day it was done on decides, not the day
    // it was posted.
    expect($this->user->refresh()->wins_count)->toBe(2);

    $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [[
            'type' => 'movement',
            'movement_type' => 'run',
            'completed_at' => '2026-07-28 06:00:00',
        ]],
    ])->assertCreated();

    expect($this->user->refresh()->wins_count)->toBe(2);
});

test('taking down one of two sittings on a day leaves the total where it was', function () {
    $this->travelTo(Carbon::parse('2026-07-28 07:00:00'));

    $morning = $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [['type' => 'meditation', 'duration_minutes' => 10]],
    ])->assertCreated()->json('data.id');

    $this->travelTo(Carbon::parse('2026-07-28 21:00:00'));

    $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [['type' => 'meditation', 'duration_minutes' => 20]],
    ])->assertCreated();

    expect($this->user->refresh()->wins_count)->toBe(1);

    /*
     * A delete recounts from the wins themselves rather than nudging the
     * column, so this is the other half of the rule: the recount has to reach
     * the same answer the increment did, and the day survives losing one of
     * the two sittings that made it.
     */
    $this->deleteJson(route('api.v1.posts.destroy', $morning))->assertOk();

    expect($this->user->refresh()->wins_count)->toBe(1);
});

test('posting three wins at once is still one day shown up for', function () {
    $this->postJson(route('api.v1.posts.store'), [
        'visibility' => 'public',
        'wins' => [
            ['type' => 'meditation', 'duration_minutes' => 10],
            ['type' => 'learning', 'learned_text' => 'Streaks count days.'],
            ['type' => 'movement', 'movement_type' => 'run'],
        ],
    ])->assertCreated();

    $this->user->refresh();

    expect($this->user->streak_days)->toBe(1);
    expect($this->user->wins_count)->toBe(3);
});
