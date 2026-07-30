<?php

namespace Database\Factories;

use App\Models\Circle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * @extends Factory<Circle>
 */
class CircleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Wrapped before joining: the generator is typed as returning either a
        // list of words or one string, and `Arr::wrap` handles both without
        // asserting which came back.
        $name = Str::title(implode(' ', Arr::wrap(fake()->unique()->words(2))));

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
