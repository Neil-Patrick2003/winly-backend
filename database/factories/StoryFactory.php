<?php

namespace Database\Factories;

use App\Models\Story;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Story>
 */
class StoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'image_url' => 'https://cdn.winly.test/stories/'.fake()->unique()->slug(3).'.jpg',
            'caption' => fake()->sentence(6),
            'expires_at' => now()->addHours(Story::LIFETIME_HOURS),
        ];
    }

    /**
     * Indicate that the story is no longer visible.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subHour(),
        ]);
    }
}
