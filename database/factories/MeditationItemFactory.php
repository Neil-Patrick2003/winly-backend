<?php

namespace Database\Factories;

use App\Models\MeditationCategory;
use App\Models\MeditationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeditationItem>
 */
class MeditationItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->unique()->slug(3);

        return [
            'category_id' => MeditationCategory::factory(),
            'title' => fake()->unique()->sentence(3),
            'instructions' => fake()->sentence(14),
            'thumbnail' => "thumbnails/{$slug}.jpg",
            'audio_url' => "https://cdn.winly.test/audio/{$slug}.mp3",
            'video_url' => null,
            'duration_minutes' => fake()->numberBetween(3, 45),
            'created_by' => null,
        ];
    }

    /**
     * Indicate that the meditation has a video track.
     */
    public function withVideo(): static
    {
        return $this->state(fn (array $attributes) => [
            'video_url' => 'https://cdn.winly.test/video/'.fake()->unique()->slug(3).'.mp4',
        ]);
    }

    /**
     * Indicate that the meditation belongs to the given category.
     */
    public function inCategory(MeditationCategory $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category->id,
        ]);
    }
}
