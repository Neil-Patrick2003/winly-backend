<?php

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('users have profile columns', function () {
    $user = User::factory()->create([
        'username' => 'winly',
        'avatar' => 'avatars/winly.jpg',
        'cover_photo' => 'covers/winly.jpg',
        'bio' => 'Hello there.',
        'is_private' => true,
    ]);

    expect($user->fresh())
        ->username->toBe('winly')
        ->avatar->toBe('avatars/winly.jpg')
        ->cover_photo->toBe('covers/winly.jpg')
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
