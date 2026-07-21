<?php

namespace Database\Factories;

use App\Models\MeditationCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeditationCategory>
 */
class MeditationCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'icon' => fake()->randomElement(MeditationCategory::ICONS),
            'description' => fake()->sentence(12),
        ];
    }

    /**
     * Indicate that the category has no description.
     */
    public function withoutDescription(): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => null,
        ]);
    }
}
