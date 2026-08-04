<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        /*
         * No stock admin here. `test@example.com` with the factory's password
         * used to be seeded with `is_admin`, which was harmless while there was
         * nothing behind the flag and is not now: the staff screens can read
         * every circle and reset anybody's password. The one staff account
         * comes from `AdminSeeder`, whose credentials can be set per
         * deployment.
         */
        $this->call([
            AdminSeeder::class,
            SocialGraphSeeder::class,
        ]);
    }
}
