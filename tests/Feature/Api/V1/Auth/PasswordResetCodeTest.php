<?php

use App\Actions\SendPasswordResetCode;
use App\Models\User;
use App\Notifications\ResetPasswordCode;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    $this->user = User::factory()->create([
        'username' => 'neil_m',
        'email' => 'neil@example.com',
    ]);
});

/**
 * The six digits that were emailed to this user.
 */
function sentCode(User $user): string
{
    $code = null;

    Notification::assertSentTo(
        $user,
        ResetPasswordCode::class,
        function (ResetPasswordCode $notification) use (&$code): bool {
            $code = $notification->code;

            return true;
        },
    );

    return $code;
}

/**
 * Ask for a code and read back the one that was sent.
 */
function requestCode(User $user): string
{
    test()->postJson(route('api.v1.password.email'), ['email' => $user->email])->assertOk();

    return sentCode($user);
}

test('requesting a reset code emails one', function () {
    $this->postJson(route('api.v1.password.email'), ['email' => 'neil@example.com'])
        ->assertOk()
        ->assertJsonStructure(['message']);

    Notification::assertSentTo($this->user, ResetPasswordCode::class);

    expect(sentCode($this->user))->toMatch('/^\d{6}$/');
});

test('the code is stored hashed rather than in the clear', function () {
    $code = requestCode($this->user);

    $record = DB::table('password_reset_tokens')->where('email', 'neil@example.com')->first();

    expect($record)->not->toBeNull();
    expect($record->token)->not->toBe($code);
    expect(Hash::check($code, $record->token))->toBeTrue();
});

test('requesting a code for an unknown address answers the same and sends nothing', function () {
    $this->postJson(route('api.v1.password.email'), ['email' => 'nobody@example.com'])
        ->assertOk()
        ->assertJsonStructure(['message']);

    Notification::assertNothingSent();
    expect(DB::table('password_reset_tokens')->count())->toBe(0);
});

test('a soft deleted user is not sent a code', function () {
    $this->user->delete();

    $this->postJson(route('api.v1.password.email'), ['email' => 'neil@example.com'])->assertOk();

    Notification::assertNothingSent();
});

test('requesting a code requires a well formed address', function () {
    $this->postJson(route('api.v1.password.email'), ['email' => 'not-an-address'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('a second code is not sent within the throttle window', function () {
    $this->postJson(route('api.v1.password.email'), ['email' => 'neil@example.com'])->assertOk();
    $this->postJson(route('api.v1.password.email'), ['email' => 'neil@example.com'])->assertOk();

    Notification::assertSentToTimes($this->user, ResetPasswordCode::class, 1);
});

test('a failed send leaves nothing behind to throttle the next attempt', function () {
    // A refused recipient, a bad API key, a relay that is down — all of it
    // surfaces here as the dispatch throwing.
    $this->mock(
        Dispatcher::class,
        fn ($mock) => $mock->shouldReceive('send')->once()->andThrow(new RuntimeException('relay refused')),
    );

    expect(fn () => app(SendPasswordResetCode::class)->handle($this->user))
        ->toThrow(RuntimeException::class);

    // No row, so the retry a moment later is not mistaken for a resend of a
    // code that never arrived.
    expect(DB::table('password_reset_tokens')->where('email', 'neil@example.com')->count())->toBe(0);
    expect(app(SendPasswordResetCode::class)->throttled($this->user))->toBeFalse();
});

test('the code email is queued rather than sent inside the request', function () {
    // The SMTP handoff is the slowest thing in the endpoint and nothing in the
    // response depends on it, so the request must not be waiting on it.
    $this->postJson(route('api.v1.password.email'), ['email' => 'neil@example.com'])->assertOk();

    Notification::assertSentTo(
        $this->user,
        ResetPasswordCode::class,
        fn (ResetPasswordCode $notification): bool => $notification instanceof ShouldQueue,
    );
});

test('a send given up on by the worker leaves nothing behind to throttle the next attempt', function () {
    $code = requestCode($this->user);

    // What the queue does after the last retry: the row is still there, and
    // clearing it is the only thing standing between the person and a retry
    // that gets silently swallowed by the throttle.
    (new ResetPasswordCode($code, 'neil@example.com'))->failed(new RuntimeException('relay refused'));

    expect(DB::table('password_reset_tokens')->where('email', 'neil@example.com')->count())->toBe(0);
    expect(app(SendPasswordResetCode::class)->throttled($this->user))->toBeFalse();
});

test('a late failure does not clear a newer code that replaced it', function () {
    $stale = requestCode($this->user);

    $this->travel(SendPasswordResetCode::THROTTLE_SECONDS + 1)->seconds();
    $this->postJson(route('api.v1.password.email'), ['email' => 'neil@example.com'])->assertOk();

    // The first send is given up on only now, after a second code has taken
    // its place. Clearing by address alone would take a live code away from
    // somebody already typing it in.
    (new ResetPasswordCode($stale, 'neil@example.com'))->failed(new RuntimeException('relay refused'));

    expect(DB::table('password_reset_tokens')->where('email', 'neil@example.com')->count())->toBe(1);
});

test('another code can be sent once the throttle window has passed', function () {
    $this->postJson(route('api.v1.password.email'), ['email' => 'neil@example.com'])->assertOk();

    $this->travel(SendPasswordResetCode::THROTTLE_SECONDS + 1)->seconds();

    $this->postJson(route('api.v1.password.email'), ['email' => 'neil@example.com'])->assertOk();

    Notification::assertSentToTimes($this->user, ResetPasswordCode::class, 2);
});

test('a valid code sets a new password and signs the device in', function () {
    $code = requestCode($this->user);

    $response = $this->postJson(route('api.v1.password.update'), [
        'email' => 'neil@example.com',
        'code' => $code,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
        'device_name' => 'iPhone 17',
    ]);

    $response->assertOk()
        ->assertJsonPath('user.id', $this->user->id)
        ->assertJsonStructure(['user' => ['id', 'email'], 'token']);

    expect(Hash::check('a-brand-new-password', $this->user->fresh()->getAuthPassword()))->toBeTrue();
});

test('the new password works at login and the old one no longer does', function () {
    $code = requestCode($this->user);

    $this->postJson(route('api.v1.password.update'), [
        'email' => 'neil@example.com',
        'code' => $code,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
        'device_name' => 'iPhone 17',
    ])->assertOk();

    $this->postJson(route('api.v1.login'), [
        'email' => 'neil@example.com',
        'password' => 'password',
        'device_name' => 'iPhone 17',
    ])->assertUnprocessable();

    $this->postJson(route('api.v1.login'), [
        'email' => 'neil@example.com',
        'password' => 'a-brand-new-password',
        'device_name' => 'iPhone 17',
    ])->assertOk();
});

test('resetting revokes every token issued before it', function () {
    $this->user->createToken('Old iPhone');
    $this->user->createToken('Old iPad');

    $code = requestCode($this->user);

    $token = $this->postJson(route('api.v1.password.update'), [
        'email' => 'neil@example.com',
        'code' => $code,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
        'device_name' => 'iPhone 17',
    ])->assertOk()->json('token');

    // Only the one just issued to the device that did the reset.
    $names = $this->user->fresh()->tokens()->pluck('name');
    expect($names)->toHaveCount(1);
    expect($names->first())->toBe('iPhone 17');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson(route('api.v1.user'))
        ->assertOk();
});

test('a code cannot be used twice', function () {
    $code = requestCode($this->user);

    $this->postJson(route('api.v1.password.update'), [
        'email' => 'neil@example.com',
        'code' => $code,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
        'device_name' => 'iPhone 17',
    ])->assertOk();

    $this->postJson(route('api.v1.password.update'), [
        'email' => 'neil@example.com',
        'code' => $code,
        'password' => 'another-new-password',
        'password_confirmation' => 'another-new-password',
        'device_name' => 'iPhone 17',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    expect(Hash::check('a-brand-new-password', $this->user->fresh()->getAuthPassword()))->toBeTrue();
});

test('an expired code is rejected', function () {
    $code = requestCode($this->user);

    $this->travel(SendPasswordResetCode::EXPIRES_MINUTES + 1)->minutes();

    $this->postJson(route('api.v1.password.update'), [
        'email' => 'neil@example.com',
        'code' => $code,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
        'device_name' => 'iPhone 17',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    expect(Hash::check('password', $this->user->fresh()->getAuthPassword()))->toBeTrue();
});

test('a wrong code is rejected', function () {
    $code = requestCode($this->user);
    $wrong = str_pad((string) ((((int) $code) + 1) % 1000000), 6, '0', STR_PAD_LEFT);

    $this->postJson(route('api.v1.password.update'), [
        'email' => 'neil@example.com',
        'code' => $wrong,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
        'device_name' => 'iPhone 17',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    expect(Hash::check('password', $this->user->fresh()->getAuthPassword()))->toBeTrue();
});

test('a code issued for one account cannot reset another', function () {
    $other = User::factory()->create(['username' => 'someone_else', 'email' => 'other@example.com']);
    $code = requestCode($other);

    $this->postJson(route('api.v1.password.update'), [
        'email' => 'neil@example.com',
        'code' => $code,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
        'device_name' => 'iPhone 17',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    expect(Hash::check('password', $this->user->fresh()->getAuthPassword()))->toBeTrue();
});

test('resetting for an unknown address is rejected the same way as a bad code', function () {
    $this->postJson(route('api.v1.password.update'), [
        'email' => 'nobody@example.com',
        'code' => '123456',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
        'device_name' => 'iPhone 17',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');
});

test('resetting requires all fields', function () {
    $this->postJson(route('api.v1.password.update'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'code', 'password', 'device_name']);
});

test('resetting requires the password to be confirmed and long enough', function () {
    $code = requestCode($this->user);

    $this->postJson(route('api.v1.password.update'), [
        'email' => 'neil@example.com',
        'code' => $code,
        'password' => 'short',
        'password_confirmation' => 'mismatched',
        'device_name' => 'iPhone 17',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');
});

test('the address is locked after five wrong codes', function () {
    requestCode($this->user);

    foreach (range(1, 5) as $attempt) {
        $this->postJson(route('api.v1.password.update'), [
            'email' => 'neil@example.com',
            'code' => '000000',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
            'device_name' => 'iPhone 17',
        ])->assertUnprocessable();
    }

    $this->postJson(route('api.v1.password.update'), [
        'email' => 'neil@example.com',
        'code' => '000000',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
        'device_name' => 'iPhone 17',
    ])->assertStatus(429);
});
