<?php

namespace Database\Factories;

use App\Models\Habit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Habit>
 */
class HabitFactory extends Factory
{
    /**
     * The goal, unit and icon each habit type ships with.
     *
     * @var array<string, array{daily_goal: int, unit: string, icon: string}>
     */
    protected array $presets = [
        'water' => ['daily_goal' => 8, 'unit' => 'glasses', 'icon' => 'droplet'],
        'steps' => ['daily_goal' => 10000, 'unit' => 'steps', 'icon' => 'footprints'],
        'sleep' => ['daily_goal' => 8, 'unit' => 'hours', 'icon' => 'moon'],
        'meditation' => ['daily_goal' => 15, 'unit' => 'minutes', 'icon' => 'brain'],
        'reading' => ['daily_goal' => 30, 'unit' => 'minutes', 'icon' => 'book-open'],
        'exercise' => ['daily_goal' => 45, 'unit' => 'minutes', 'icon' => 'dumbbell'],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(Habit::TYPES);

        return [
            'user_id' => User::factory(),
            'type' => $type,
            ...$this->presets[$type],
            'color_hex' => fake()->hexColor(),
        ];
    }

    /**
     * Indicate which habit is being tracked.
     */
    public function ofType(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
            ...$this->presets[$type] ?? [],
        ]);
    }
}
