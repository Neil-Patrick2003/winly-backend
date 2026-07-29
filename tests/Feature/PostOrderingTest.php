<?php

use App\Models\Circle;
use App\Models\Post;

/*
 * These assert on the SQL rather than on results, because the fault they guard
 * against does not show up as a wrong answer on SQLite: an unqualified
 * `created_at` in a join is ambiguous, and SQLite resolves it silently while
 * MySQL refuses the query. Only the generated SQL tells the two apart.
 */

test('ordering posts qualifies its columns', function () {
    $sql = Post::query()->latestFirst()->toSql();

    expect($sql)->toContain('"posts"."created_at" desc')
        ->and($sql)->toContain('"posts"."id" desc');
});

test('ordering a circles posts is not ambiguous between the pivot and the post', function () {
    $circle = Circle::factory()->create();

    $sql = $circle->posts()->latestFirst()->toSql();

    // `circle_post` carries a created_at of its own, so a bare column here
    // would be ambiguous the moment the pivot is joined.
    expect($sql)->toContain('circle_post')
        ->and($sql)->toContain('"posts"."created_at" desc')
        ->and($sql)->not->toMatch('/order by "created_at"/');
});
