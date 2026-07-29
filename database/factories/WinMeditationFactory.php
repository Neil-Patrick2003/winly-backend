<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\WinMeditation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WinMeditation>
 */
class WinMeditationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'duration_minutes' => fake()->numberBetween(3, 45),
            'completed' => true,
            'media_attached' => false,
            'completed_at' => now(),
        ];
    }

    /**
     * Indicate that the timer was stopped before it finished.
     */
    public function cutShort(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed' => false,
        ]);
    }

    /**
     * Indicate that the win came with a photo or clip.
     */
    public function withMedia(): static
    {
        return $this->state(fn (array $attributes) => [
            'media_attached' => true,
        ]);
    }
}
