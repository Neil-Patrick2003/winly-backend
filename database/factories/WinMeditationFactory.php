<?php

namespace Database\Factories;

use App\Models\MeditationItem;
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
            'meditation_item_id' => MeditationItem::factory(),
            'media_attached' => false,
            'completed_at' => now(),
        ];
    }

    /**
     * Indicate that the session was not one from the library.
     */
    public function unguided(): static
    {
        return $this->state(fn (array $attributes) => [
            'meditation_item_id' => null,
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
