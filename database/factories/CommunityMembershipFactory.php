<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\CommunityMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityMembership>
 */
class CommunityMembershipFactory extends Factory
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
            'community_id' => Community::factory(),
            'joined_at' => now(),
        ];
    }
}
