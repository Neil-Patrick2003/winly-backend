<?php

namespace Database\Factories;

use App\Models\Habit;
use App\Models\HabitLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HabitLog>
 */
class HabitLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'habit_id' => Habit::factory(),
            'user_id' => fn (array $attributes) => Habit::find($attributes['habit_id'])?->user_id,
            'date' => now()->toDateString(),
            'value_logged' => fake()->numberBetween(1, 10),
            'completed' => false,
            'logged_at' => now(),
        ];
    }

    /**
     * Indicate that the day's goal was met.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed' => true,
        ]);
    }

    /**
     * Indicate which day the entry covers.
     */
    public function on(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => $date,
        ]);
    }
}
