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
     * Representative values the mobile clients send, for seeding only.
     *
     * `movement_type` is free text, so this list is illustrative rather than
     * exhaustive.
     *
     * @var list<string>
     */
    private const SAMPLE_TYPES = ['walk', 'run', 'cycle', 'swim', 'gym', 'yoga', 'stretch', 'other'];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'movement_type' => fake()->randomElement(self::SAMPLE_TYPES),
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
