<?php

namespace Database\Factories;

use App\Models\Follow;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Follow>
 */
class FollowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'follower_id' => User::factory(),
            'followee_id' => User::factory(),
        ];
    }

    /**
     * Indicate who is doing the following.
     */
    public function from(User $follower): static
    {
        return $this->state(fn (array $attributes) => [
            'follower_id' => $follower->id,
        ]);
    }

    /**
     * Indicate who is being followed.
     */
    public function to(User $followee): static
    {
        return $this->state(fn (array $attributes) => [
            'followee_id' => $followee->id,
        ]);
    }
}
