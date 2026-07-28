<?php

namespace Database\Seeders;

use App\Models\User;
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
        // User::factory(10)->create();

        User::factory()->admin()->create([
            'full_name' => 'Test User',
            'username' => 'test_user',
            'email' => 'test@example.com',
        ]);

        $this->call([
            MeditationCategorySeeder::class,
            MeditationSeeder::class,
            SocialGraphSeeder::class,
        ]);
    }
}
