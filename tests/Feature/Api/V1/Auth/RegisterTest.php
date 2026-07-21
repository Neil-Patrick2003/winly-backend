<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Neil Mulingbayan',
        'username' => 'neil_m',
        'email' => 'neil@example.com',
        'password' => 'password-password',
        'password_confirmation' => 'password-password',
        'device_name' => 'iPhone 17',
    ], $overrides);
}

test('a user can register and receive an api token', function () {
    Event::fake(Registered::class);

    $response = $this->postJson(route('api.v1.register'), registrationPayload());

    $response->assertCreated()
        ->assertJsonPath('user.name', 'Neil Mulingbayan')
        ->assertJsonPath('user.username', 'neil_m')
        ->assertJsonPath('user.email', 'neil@example.com')
        ->assertJsonPath('user.is_private', false)
        ->assertJsonStructure(['user' => ['id', 'created_at'], 'token'])
        ->assertJsonMissingPath('user.password');

    $user = User::firstWhere('email', 'neil@example.com');

    expect($user)->not->toBeNull();
    expect(Hash::check('password-password', $user->password))->toBeTrue();

    Event::assertDispatched(Registered::class);

    $token = PersonalAccessToken::findToken($response->json('token'));

    expect($token)->not->toBeNull();
    expect($token->name)->toBe('iPhone 17');
    expect($token->tokenable->is($user))->toBeTrue();
});

test('the issued token authenticates api requests', function () {
    $token = $this->postJson(route('api.v1.register'), registrationPayload())->json('token');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson(route('api.v1.user'))
        ->assertOk()
        ->assertJsonPath('data.username', 'neil_m');
});

test('guests cannot access protected api routes', function () {
    $this->getJson(route('api.v1.user'))->assertUnauthorized();
});

test('registration requires all fields', function () {
    $this->postJson(route('api.v1.register'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'username', 'email', 'password', 'device_name']);
});

test('the email must be unique', function () {
    User::factory()->create(['email' => 'neil@example.com']);

    $this->postJson(route('api.v1.register'), registrationPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('the username must be unique', function () {
    User::factory()->create(['username' => 'neil_m']);

    $this->postJson(route('api.v1.register'), registrationPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('username');
});

test('the username is normalized to lowercase', function () {
    $this->postJson(route('api.v1.register'), registrationPayload(['username' => '  Neil_M  ']))
        ->assertCreated()
        ->assertJsonPath('user.username', 'neil_m');
});

test('the username rejects invalid characters', function (string $username) {
    $this->postJson(route('api.v1.register'), registrationPayload(['username' => $username]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('username');
})->with([
    'spaces' => 'neil m',
    'dashes' => 'neil-m',
    'symbols' => 'neil@m',
    'too short' => 'ne',
    'too long' => 'n_______________________________',
]);

test('the password must be confirmed', function () {
    $this->postJson(route('api.v1.register'), registrationPayload(['password_confirmation' => 'different-password']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');
});

test('registration is rate limited', function () {
    foreach (range(1, 6) as $attempt) {
        $this->postJson(route('api.v1.register'), registrationPayload([
            'username' => "neil_{$attempt}",
            'email' => "neil{$attempt}@example.com",
        ]))->assertCreated();
    }

    $this->postJson(route('api.v1.register'), registrationPayload([
        'username' => 'neil_7',
        'email' => 'neil7@example.com',
    ]))->assertStatus(429);
});
