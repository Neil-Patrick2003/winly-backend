<?php

use App\Models\Circle;
use App\Models\Follow;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Write a post and share it into a circle.
 *
 * A post reaches a circle through the `circle_post` pivot rather than a column
 * on the post, so creating one takes two steps everywhere it is done.
 *
 * Shared into the circle and nowhere else, which is what placing a win in a
 * group now means: only its members may read it. Pass `visibility` to override
 * where a test wants a public post that also happens to sit in a circle.
 *
 * @param  array<string, mixed>  $attributes
 */
function postInCircle(
    Circle $circle,
    User $author,
    array $attributes = [],
): Post {
    $post = Post::factory()->create([
        'user_id' => $author->id,
        'visibility' => Post::VISIBILITY_CUSTOM,
        ...$attributes,
    ]);

    $circle->posts()->attach($post);

    return $post;
}

/**
 * Make a mutual follow — the app's definition of a friend, and what the circle
 * invite pickers draw their candidates from.
 */
function befriend(User $a, User $b): void
{
    Follow::factory()->from($a)->to($b)->create();
    Follow::factory()->from($b)->to($a)->create();
}

/**
 * A moment on the clock days are measured on.
 *
 * Day boundaries are cut on the display timezone, not UTC, so times authored
 * in UTC straddle the wrong midnight — at UTC+8, six in the evening UTC is two
 * the next morning, and a win written that way lands on the day after the one
 * the test means.
 */
function atLocal(string $time): Carbon
{
    return Carbon::parse($time, config('app.display_timezone'));
}

/**
 * The same moment, ready to be written to a column.
 *
 * Eloquent stores whatever wall clock the instance carries and reads it back
 * as UTC, so a local time assigned straight to an attribute lands eight hours
 * out. Converting first keeps the stored instant the one that was meant.
 */
function storedAtLocal(string $time): Carbon
{
    return atLocal($time)->utc();
}
