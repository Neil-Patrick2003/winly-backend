<?php

namespace Database\Factories;

use App\Models\Circle;
use App\Models\CircleInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CircleInvitation>
 */
class CircleInvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'circle_id' => Circle::factory(),
            'inviter_id' => User::factory(),
            'invitee_id' => User::factory(),
            'status' => CircleInvitation::PENDING,
            'responded_at' => null,
        ];
    }
}
