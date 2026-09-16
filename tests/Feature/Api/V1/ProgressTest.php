<?php

use App\Models\Post;
use App\Models\User;
use App\Models\WinLearning;
use App\Models\WinMeditation;
use App\Models\WinMovement;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

test('guests cannot read the week', function () {
    app()['auth']->forgetGuards();

    $this->getJson(route('api.v1.progress.week'))->assertUnauthorized();
});

test('the week runs Monday to Sunday around today', function () {
    // A Wednesday.
    Carbon::setTestNow(atLocal('2026-07-29 10:00:00'));

    $response = $this->getJson(route('api.v1.progress.week'))->assertOk();

    expect($response->json('data.start'))->toBe('2026-07-27')
        ->and($response->json('data.end'))->toBe('2026-08-02')
        ->and($response->json('data.days'))->toHaveCount(7);

    $weekdays = collect($response->json('data.days'))->pluck('weekday');
    expect($weekdays->all())->toBe(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']);
});

test('each day says which kinds of win were logged on it', function () {
    Carbon::setTestNow(atLocal('2026-07-29 10:00:00'));

    $monday = Post::factory()->for($this->user)->create();
    WinMeditation::factory()->for($monday, 'post')->create([
        'completed_at' => storedAtLocal('2026-07-27 07:00:00'),
    ]);
    WinMovement::factory()->for($monday, 'post')->create([
        'completed_at' => storedAtLocal('2026-07-27 18:00:00'),
    ]);

    $wednesday = Post::factory()->for($this->user)->create();
    WinLearning::factory()->for($wednesday, 'post')->create([
        'completed_at' => storedAtLocal('2026-07-29 09:00:00'),
    ]);

    $days = collect($this->getJson(route('api.v1.progress.week'))->assertOk()->json('data.days'))
        ->keyBy('date');

    expect($days['2026-07-27'])->toMatchArray([
        'meditation' => true,
        'learning' => false,
        'movement' => true,
        'is_today' => false,
        'is_future' => false,
    ]);

    expect($days['2026-07-29'])->toMatchArray([
        'meditation' => false,
        'learning' => true,
        'movement' => false,
        'is_today' => true,
        'is_future' => false,
    ]);

    // Thursday has not happened yet, and is drawn differently for it.
    expect($days['2026-07-30']['is_future'])->toBeTrue();
});

test('two wins of the same kind on one day is still one day', function () {
    Carbon::setTestNow(atLocal('2026-07-29 10:00:00'));

    foreach ([8, 20] as $hour) {
        $post = Post::factory()->for($this->user)->create();
        WinMeditation::factory()->for($post, 'post')->create([
            'completed_at' => storedAtLocal("2026-07-29 {$hour}:00:00"),
        ]);
    }

    $days = collect($this->getJson(route('api.v1.progress.week'))->assertOk()->json('data.days'))
        ->keyBy('date');

    expect($days['2026-07-29']['meditation'])->toBeTrue();
});

test('a win is placed by when it was done, not when it was posted', function () {
    Carbon::setTestNow(atLocal('2026-07-29 10:00:00'));

    // Written on Wednesday about Monday's walk.
    $post = Post::factory()->for($this->user)->create(['created_at' => Carbon::parse('2026-07-29 09:00:00')]);
    WinMovement::factory()->for($post, 'post')->create([
        'completed_at' => storedAtLocal('2026-07-27 17:00:00'),
    ]);

    $days = collect($this->getJson(route('api.v1.progress.week'))->assertOk()->json('data.days'))
        ->keyBy('date');

    expect($days['2026-07-27']['movement'])->toBeTrue()
        ->and($days['2026-07-29']['movement'])->toBeFalse();
});

test('other peoples wins and other weeks stay out of it', function () {
    Carbon::setTestNow(atLocal('2026-07-29 10:00:00'));

    $mine = Post::factory()->for($this->user)->create();
    WinMeditation::factory()->for($mine, 'post')->create([
        // Last week.
        'completed_at' => storedAtLocal('2026-07-22 07:00:00'),
    ]);

    $theirs = Post::factory()->for(User::factory())->create();
    WinLearning::factory()->for($theirs, 'post')->create([
        'completed_at' => storedAtLocal('2026-07-29 07:00:00'),
    ]);

    $days = collect($this->getJson(route('api.v1.progress.week'))->assertOk()->json('data.days'));

    expect($days->contains(fn (array $day): bool => $day['meditation']))->toBeFalse()
        ->and($days->contains(fn (array $day): bool => $day['learning']))->toBeFalse();
});

test('the week carries the streak the header shows', function () {
    Carbon::setTestNow(atLocal('2026-07-29 10:00:00'));

    // Forced rather than filled: the streak columns are guarded so that only
    // `RecordWinStreak` moves them, which is exactly what a mass assignment
    // here would quietly work around.
    $this->user->forceFill([
        'streak_days' => 5,
        'longest_streak' => 11,
        'last_win_on' => Carbon::parse('2026-07-29'),
    ])->save();

    $this->getJson(route('api.v1.progress.week'))
        ->assertOk()
        ->assertJsonPath('data.streak_days', 5)
        ->assertJsonPath('data.longest_streak', 11);
});

test('a streak that has already lapsed is reported as broken', function () {
    Carbon::setTestNow(atLocal('2026-07-29 10:00:00'));

    $this->user->forceFill([
        'streak_days' => 5,
        // Nothing since last week: the run is over, whatever the column says.
        'last_win_on' => Carbon::parse('2026-07-20'),
    ])->save();

    $this->getJson(route('api.v1.progress.week'))
        ->assertOk()
        ->assertJsonPath('data.streak_days', 0);
});

test('guests cannot read a range', function () {
    app()['auth']->forgetGuards();

    $this->getJson(route('api.v1.progress.range', ['start' => '2026-07-01']))->assertUnauthorized();
});

test('a range answers for the window asked for, a day at a time', function () {
    Carbon::setTestNow(atLocal('2026-07-29 10:00:00'));

    $response = $this->getJson(route('api.v1.progress.range', [
        'start' => '2026-07-01',
        'end' => '2026-07-10',
    ]))->assertOk();

    expect($response->json('data.start'))->toBe('2026-07-01')
        ->and($response->json('data.end'))->toBe('2026-07-10')
        ->and($response->json('data.days'))->toHaveCount(10)
        ->and($response->json('data.days.0.date'))->toBe('2026-07-01')
        ->and($response->json('data.days.9.date'))->toBe('2026-07-10');
});

test('a range reaches days the current week no longer covers', function () {
    Carbon::setTestNow(atLocal('2026-07-29 10:00:00'));

    // Three weeks before the week `week` would answer for.
    $post = Post::factory()->for($this->user)->create();
    WinMovement::factory()->for($post, 'post')->create([
        'completed_at' => storedAtLocal('2026-07-08 18:00:00'),
    ]);

    $days = collect($this->getJson(route('api.v1.progress.range', [
        'start' => '2026-07-01',
        'end' => '2026-07-10',
    ]))->assertOk()->json('data.days'))->keyBy('date');

    expect($days['2026-07-08'])->toMatchArray([
        'movement' => true,
        'meditation' => false,
        'learning' => false,
        'is_future' => false,
    ]);
});

test('the end of a range defaults to today', function () {
    Carbon::setTestNow(atLocal('2026-07-29 10:00:00'));

    $response = $this->getJson(route('api.v1.progress.range', ['start' => '2026-07-27']))->assertOk();

    expect($response->json('data.end'))->toBe('2026-07-29')
        ->and($response->json('data.days'))->toHaveCount(3)
        ->and(collect($response->json('data.days'))->last()['is_today'])->toBeTrue();
});

test('a range carries the same streak figures as the week', function () {
    Carbon::setTestNow(atLocal('2026-07-29 10:00:00'));

    $response = $this->getJson(route('api.v1.progress.range', ['start' => '2026-07-27']))->assertOk();

    expect($response->json('data'))->toHaveKeys(['streak_days', 'longest_streak']);
});

test('a range needs a start', function () {
    $this->getJson(route('api.v1.progress.range'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('start');
});

test('a range will not run backwards', function () {
    $this->getJson(route('api.v1.progress.range', ['start' => '2026-07-10', 'end' => '2026-07-01']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('end');
});

test('a range is capped at a year', function () {
    Carbon::setTestNow(atLocal('2026-07-29 10:00:00'));

    // Unbounded windows are how one caller leaving the end off turns into a
    // scan of every win the account has ever logged.
    $this->getJson(route('api.v1.progress.range', ['start' => '2020-01-01', 'end' => '2026-07-29']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('end');
});
