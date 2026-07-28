<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'actor_id' => User::factory(),
            'type' => fake()->randomElement(Notification::TYPES),
            'post_id' => null,
            'message' => fake()->sentence(6),
            'is_read' => false,
        ];
    }

    /**
     * Indicate that the recipient has opened the notification.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => true,
        ]);
    }

    /**
     * Indicate that the notification was raised by the system, not a person.
     */
    public function fromSystem(): static
    {
        return $this->state(fn (array $attributes) => [
            'actor_id' => null,
        ]);
    }
}
