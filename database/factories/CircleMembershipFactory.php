<?php

namespace Database\Factories;

use App\Models\Circle;
use App\Models\CircleMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CircleMembership>
 */
class CircleMembershipFactory extends Factory
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
            'circle_id' => Circle::factory(),
            'joined_at' => now(),
        ];
    }
}
