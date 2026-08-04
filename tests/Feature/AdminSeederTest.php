<?php

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Support\Facades\Hash;

test('it seeds a staff account that can sign in', function () {
    $this->seed(AdminSeeder::class);

    $admin = User::where('email', 'welle_admin@gmail.com')->first();

    expect($admin)->not->toBeNull();
    expect($admin->is_admin)->toBeTrue();
    // Verified, or every signed-in route turns it away at the door.
    expect($admin->email_verified_at)->not->toBeNull();
    expect(Hash::check('welle_metadigitrading', $admin->password_hash))->toBeTrue();
});

test('the seeded account reaches the admin screens', function () {
    $this->seed(AdminSeeder::class);

    // The whole reason the account exists, so it is worth proving rather than
    // inferring from the column.
    $this->actingAs(User::where('email', 'welle_admin@gmail.com')->first())
        ->get(route('admin.circles'))
        ->assertOk();
});

test('running it twice updates the account rather than failing', function () {
    $this->seed(AdminSeeder::class);
    $this->seed(AdminSeeder::class);

    // The email and the username are both unique, so a second run would fall
    // over if it were not keyed on the email.
    expect(User::where('email', 'welle_admin@gmail.com')->count())->toBe(1);
});

test('the credentials can be overridden for a deployment', function () {
    /*
     * Read through the config rather than `env()` directly, so this keeps
     * working on a server that has cached its config — where `env()` would
     * fall back to the password committed to the repository.
     */
    config([
        'admin.email' => 'someone_else@example.com',
        'admin.password' => 'a-different-password',
    ]);

    $this->seed(AdminSeeder::class);

    $admin = User::where('email', 'someone_else@example.com')->first();

    expect($admin)->not->toBeNull();
    expect($admin->is_admin)->toBeTrue();
    expect(Hash::check('a-different-password', $admin->password_hash))->toBeTrue();
    expect(User::where('email', 'welle_admin@gmail.com')->exists())->toBeFalse();
});
