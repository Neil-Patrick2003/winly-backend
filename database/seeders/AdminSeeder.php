<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Put the staff account in place.
     *
     * Safe to run again: it is keyed on the email, so a second run updates the
     * account rather than failing on the unique column. That also means it
     * resets the password — which is the point when the password has been lost,
     * and worth knowing before running it against a live database where the
     * password has since been changed.
     *
     * Both values come from `config/admin.php`, so a deployment can set
     * `ADMIN_EMAIL` and `ADMIN_PASSWORD` rather than use the ones written into
     * the repository — which anyone who can read it knows.
     */
    public function run(): void
    {
        $email = (string) config('admin.email');
        $password = (string) config('admin.password');

        User::updateOrCreate(
            ['email' => $email],
            [
                'full_name' => 'Welle Admin',
                'username' => 'welle_admin',
                // Cast to `hashed` on the model, so the plain string is hashed
                // on the way in rather than stored as it stands.
                'password_hash' => $password,
                'is_admin' => true,
                // Set, or the account never gets past the `verified` middleware
                // that every signed-in route sits behind.
                'email_verified_at' => now(),
            ],
        );
    }
}
