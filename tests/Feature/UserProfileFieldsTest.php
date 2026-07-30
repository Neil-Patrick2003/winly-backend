<?php

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('users have profile columns', function () {
    $user = User::factory()->create([
        'username' => 'winly',
        'avatar_url' => 'avatars/winly.jpg',
        'cover_gradient' => 'sunrise',
        'bio' => 'Hello there.',
        'is_private' => true,
    ]);

    expect($user->fresh())
        ->username->toBe('winly')
        ->avatar_url->toBe('avatars/winly.jpg')
        ->cover_gradient->toBe('sunrise')
        ->bio->toBe('Hello there.')
        ->is_private->toBeTrue();
});

test('usernames are unique', function () {
    User::factory()->create(['username' => 'winly']);

    User::factory()->create(['username' => 'winly']);
})->throws(UniqueConstraintViolationException::class);

test('users are soft deleted', function () {
    $user = User::factory()->create();

    $user->delete();

    expect(User::find($user->id))->toBeNull();
    expect(User::withTrashed()->find($user->id))->not->toBeNull();
    $this->assertSoftDeleted($user);
});
