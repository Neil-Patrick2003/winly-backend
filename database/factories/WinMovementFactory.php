<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\WinMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WinMovement>
 */
class WinMovementFactory extends Factory
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
            'movement_type' => fake()->randomElement(WinMovement::TYPES),
            'media_attached' => false,
            'completed_at' => now(),
        ];
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
