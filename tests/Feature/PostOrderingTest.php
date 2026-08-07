<?php

use App\Models\Circle;
use App\Models\Post;

/*
 * These assert on the SQL rather than on results, because the fault they guard
 * against does not show up as a wrong answer on SQLite: an unqualified
 * `created_at` in a join is ambiguous, and SQLite resolves it silently while
 * MySQL refuses the query. Only the generated SQL tells the two apart.
 */

/**
 * The same SQL with the identifier quotes taken off.
 *
 * SQLite writes `"posts"."created_at"` and MySQL writes it in backticks, so an
 * assertion naming either one passes on one connection and fails on the other.
 * That made this file the last thing standing between the suite and a green run
 * against MySQL — over a difference that has nothing to do with what is being
 * checked here, which is only that the column is qualified at all.
 */
function unquoted(string $sql): string
{
    return str_replace(['"', '`'], '', $sql);
}

test('ordering posts qualifies its columns', function () {
    $sql = unquoted(Post::query()->latestFirst()->toSql());

    expect($sql)->toContain('posts.created_at desc')
        ->and($sql)->toContain('posts.id desc');
});

test('ordering a circles posts is not ambiguous between the pivot and the post', function () {
    $circle = Circle::factory()->create();

    $sql = unquoted($circle->posts()->latestFirst()->toSql());

    // `circle_post` carries a created_at of its own, so a bare column here
    // would be ambiguous the moment the pivot is joined.
    expect($sql)->toContain('circle_post')
        ->and($sql)->toContain('posts.created_at desc')
        // The unqualified column is the fault: it must be absent, not merely
        // accompanied by the qualified one.
        ->and($sql)->not->toMatch('/order by created_at/');
});
