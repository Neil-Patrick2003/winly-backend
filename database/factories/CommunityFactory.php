<?php

namespace Database\Factories;

use App\Models\Community;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Community>
 */
class CommunityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true));

        return [
            'name' => $name,
            'description' => fake()->sentence(12),
            'icon_initial' => Str::upper(Str::substr($name, 0, 1)),
            'color_hex' => fake()->hexColor(),
            'tag' => fake()->randomElement(['mindfulness', 'fitness', 'learning', 'sleep', 'focus']),
            'members_count' => 0,
        ];
    }
}
