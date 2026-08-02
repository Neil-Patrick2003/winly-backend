<?php

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('users have profile columns', function () {
    $user = User::factory()->create([
        'username' => 'winly',
        'cover_gradient' => 'sunrise',
        'bio' => 'Hello there.',
        'is_private' => true,
    ]);

    expect($user->fresh())
        ->username->toBe('winly')
        ->cover_gradient->toBe('sunrise')
        ->bio->toBe('Hello there.')
        ->is_private->toBeTrue();
});

test('a profile photo is read back off the media rather than a column', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    expect($user->avatar_url)->toBeNull();

    $user->addMedia(UploadedFile::fake()->image('me.jpg'))->toMediaCollection(User::AVATAR_COLLECTION);

    expect($user->fresh()->avatar_url)
        ->toStartWith('http')
        ->toEndWith('me.jpg');
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
