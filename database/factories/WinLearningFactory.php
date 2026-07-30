<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\WinLearning;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WinLearning>
 */
class WinLearningFactory extends Factory
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
            'learned_text' => fake()->sentence(14),
            'reference_source' => fake()->url(),
            'media_attached' => false,
            'completed_at' => now(),
        ];
    }

    /**
     * Indicate that the learning has no cited source.
     */
    public function withoutSource(): static
    {
        return $this->state(fn (array $attributes) => [
            'reference_source' => null,
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
