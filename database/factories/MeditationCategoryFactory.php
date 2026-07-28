<?php

namespace Database\Factories;

use App\Models\MeditationCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
        $label = fake()->unique()->words(2, true);

        return [
            'label' => Str::title($label),
            'slug' => Str::slug($label),
            'icon' => fake()->randomElement(MeditationCategory::ICONS),
            'description' => fake()->sentence(12),
            'created_by' => null,
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
