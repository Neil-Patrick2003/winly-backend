<?php

use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Models\User;
use App\Rules\MediaFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('public');
    $this->user = User::factory()->create([
        'full_name' => 'Original Name',
        'username' => 'original',
        'bio' => 'Original bio.',
        'cover_gradient' => 'sunrise',
    ]);
    Sanctum::actingAs($this->user);
});

test('guests cannot edit a profile', function () {
    app()['auth']->forgetGuards();

    $this->patchJson(route('api.v1.profile.update'), ['full_name' => 'Nobody'])
        ->assertUnauthorized();
});

test('a user can edit their profile', function () {
    $this->patchJson(route('api.v1.profile.update'), [
        'full_name' => 'New Name',
        'username' => 'new_name',
        'bio' => 'A fresh start.',
        'cover_gradient' => 'ocean',
    ])
        ->assertOk()
        ->assertJsonPath('data.full_name', 'New Name')
        ->assertJsonPath('data.username', 'new_name')
        ->assertJsonPath('data.bio', 'A fresh start.')
        ->assertJsonPath('data.cover_gradient', 'ocean')
        ->assertJsonPath('data.is_self', true);

    $this->user->refresh();

    expect($this->user->full_name)->toBe('New Name');
    expect($this->user->username)->toBe('new_name');
    expect($this->user->bio)->toBe('A fresh start.');
    expect($this->user->cover_gradient)->toBe('ocean');
});

test('an edit leaves the fields it does not mention alone', function () {
    $this->patchJson(route('api.v1.profile.update'), ['bio' => 'Only the bio.'])
        ->assertOk()
        ->assertJsonPath('data.bio', 'Only the bio.')
        ->assertJsonPath('data.full_name', 'Original Name')
        ->assertJsonPath('data.username', 'original')
        ->assertJsonPath('data.cover_gradient', 'sunrise');

    $this->user->refresh();

    expect($this->user->full_name)->toBe('Original Name');
    expect($this->user->cover_gradient)->toBe('sunrise');
});

test('a bio can be cleared', function () {
    $this->patchJson(route('api.v1.profile.update'), ['bio' => null])
        ->assertOk()
        ->assertJsonPath('data.bio', null);

    expect($this->user->refresh()->bio)->toBeNull();
});

test('a sent name cannot be blank', function () {
    $this->patchJson(route('api.v1.profile.update'), ['full_name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('full_name');

    expect($this->user->refresh()->full_name)->toBe('Original Name');
});

test('a username cannot be taken from somebody else', function () {
    User::factory()->create(['username' => 'taken']);

    $this->patchJson(route('api.v1.profile.update'), ['username' => 'taken'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('username');

    expect($this->user->refresh()->username)->toBe('original');
});

test('keeping your own username is not a clash', function () {
    $this->patchJson(route('api.v1.profile.update'), [
        'username' => 'original',
        'full_name' => 'Same Handle',
    ])->assertOk();

    expect($this->user->refresh()->full_name)->toBe('Same Handle');
});

test('a username has to be lowercase letters, numbers and underscores', function () {
    $this->patchJson(route('api.v1.profile.update'), ['username' => 'Not Allowed!'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('username');
});

test('a bio cannot run past the limit', function () {
    $this->patchJson(route('api.v1.profile.update'), [
        'bio' => str_repeat('a', UpdateProfileRequest::MAX_BIO_LENGTH + 1),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('bio');
});

test('changing the email drops the verification with it', function () {
    expect($this->user->email_verified_at)->not->toBeNull();

    $this->patchJson(route('api.v1.profile.update'), ['email' => 'moved@example.com'])
        ->assertOk()
        ->assertJsonPath('data.email', 'moved@example.com')
        ->assertJsonPath('data.email_verified_at', null);

    $this->user->refresh();

    expect($this->user->email)->toBe('moved@example.com');
    expect($this->user->email_verified_at)->toBeNull();
});

test('resending the same email leaves the verification standing', function () {
    $this->patchJson(route('api.v1.profile.update'), ['email' => $this->user->email])
        ->assertOk();

    expect($this->user->refresh()->email_verified_at)->not->toBeNull();
});

test('an email already in use is rejected', function () {
    $other = User::factory()->create();

    $this->patchJson(route('api.v1.profile.update'), ['email' => $other->email])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('an account can be made private and public again', function () {
    $this->patchJson(route('api.v1.profile.update'), ['is_private' => true])
        ->assertOk()
        ->assertJsonPath('data.is_private', true);

    expect($this->user->refresh()->is_private)->toBeTrue();

    $this->patchJson(route('api.v1.profile.update'), ['is_private' => false])
        ->assertOk()
        ->assertJsonPath('data.is_private', false);

    expect($this->user->refresh()->is_private)->toBeFalse();
});

test('a profile photo can be uploaded', function () {
    $response = $this->post(route('api.v1.profile.update'), [
        '_method' => 'PATCH',
        'avatar' => UploadedFile::fake()->image('me.jpg', 400, 400),
    ]);

    $response->assertOk();

    expect($response->json('data.avatar_url'))->toStartWith('http');
    expect(Storage::disk('public')->allFiles('media'))->toHaveCount(1);
    expect($this->user->refresh()->avatar_url)->toBe($response->json('data.avatar_url'));
});

test('replacing a profile photo takes the old file with it', function () {
    $this->post(route('api.v1.profile.update'), [
        '_method' => 'PATCH',
        'avatar' => UploadedFile::fake()->image('first.jpg'),
    ])->assertOk();

    $this->post(route('api.v1.profile.update'), [
        '_method' => 'PATCH',
        'avatar' => UploadedFile::fake()->image('second.jpg'),
    ])->assertOk();

    // The collection holds a single file, so the first photo is gone rather
    // than sitting on the disk with nothing left pointing at it.
    $files = Storage::disk('public')->allFiles('media');

    expect($files)->toHaveCount(1)
        ->and($files[0])->toEndWith('second.jpg')
        ->and($this->user->refresh()->avatar_url)->toEndWith('second.jpg');
});

test('a profile photo can be uploaded alongside other fields', function () {
    $this->post(route('api.v1.profile.update'), [
        '_method' => 'PATCH',
        'avatar' => UploadedFile::fake()->image('me.jpg'),
        'full_name' => 'Photo And Name',
    ])->assertOk()->assertJsonPath('data.full_name', 'Photo And Name');

    expect($this->user->refresh()->avatar_url)->not->toBeNull();
});

test('a profile photo can be removed', function () {
    $this->user->addMedia(UploadedFile::fake()->image('old.jpg'))->toMediaCollection(User::AVATAR_COLLECTION);

    $this->patchJson(route('api.v1.profile.update'), ['remove_avatar' => true])
        ->assertOk()
        ->assertJsonPath('data.avatar_url', null);

    expect($this->user->refresh()->avatar_url)->toBeNull()
        ->and(Storage::disk('public')->allFiles('media'))->toBeEmpty();
});

test('an edit that says nothing about the photo keeps it', function () {
    $this->user->addMedia(UploadedFile::fake()->image('keep.jpg'))->toMediaCollection(User::AVATAR_COLLECTION);

    $kept = $this->user->refresh()->avatar_url;

    $this->patchJson(route('api.v1.profile.update'), ['full_name' => 'Still Here'])
        ->assertOk()
        ->assertJsonPath('data.avatar_url', $kept);

    expect($this->user->refresh()->avatar_url)->toBe($kept);
});

test('a video cannot be used as a profile photo', function () {
    $this->post(route('api.v1.profile.update'), [
        '_method' => 'PATCH',
        'avatar' => UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4'),
    ])->assertUnprocessable()->assertJsonValidationErrors('avatar');

    expect(Storage::disk('public')->allFiles('media'))->toBeEmpty();
});

test('an oversized profile photo is rejected', function () {
    $this->post(route('api.v1.profile.update'), [
        '_method' => 'PATCH',
        'avatar' => UploadedFile::fake()->create('huge.jpg', MediaFile::MAX_IMAGE_KB + 1, 'image/jpeg'),
    ])->assertUnprocessable()->assertJsonValidationErrors('avatar');
});

test('the counters and streak cannot be edited', function () {
    $this->user->forceFill([
        'wins_count' => 5,
        'followers_count' => 2,
        'following_count' => 1,
        'streak_days' => 3,
        'longest_streak' => 3,
        'last_win_on' => today(),
    ])->save();

    $this->patchJson(route('api.v1.profile.update'), [
        'wins_count' => 9999,
        'followers_count' => 9999,
        'following_count' => 9999,
        'streak_days' => 9999,
        'longest_streak' => 9999,
    ])->assertOk();

    $this->user->refresh();

    expect($this->user->wins_count)->toBe(5);
    expect($this->user->followers_count)->toBe(2);
    expect($this->user->following_count)->toBe(1);
    expect($this->user->streak_days)->toBe(3);
    expect($this->user->longest_streak)->toBe(3);
});

test('a user cannot make themselves an administrator', function () {
    $this->patchJson(route('api.v1.profile.update'), [
        'full_name' => 'Sneaky',
        'is_admin' => true,
    ])->assertOk();

    expect($this->user->refresh()->is_admin)->toBeFalse();
});

test('an edit only ever touches the signed-in user', function () {
    $other = User::factory()->create(['full_name' => 'Untouched']);

    $this->patchJson(route('api.v1.profile.update'), [
        'id' => $other->id,
        'full_name' => 'Mine',
    ])->assertOk();

    expect($this->user->refresh()->full_name)->toBe('Mine');
    expect($other->refresh()->full_name)->toBe('Untouched');
});
