<?php

use App\Models\User;
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
    expect($this->user->wins_count)->toBe(3);
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

test('posting three wins at once is still one day shown up for', function () {
    $this->postJson(route('api.v1.posts.store'), [
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
