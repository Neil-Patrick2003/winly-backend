<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
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
            'caption' => fake()->sentence(10),
            // Public by default so a factory post is readable by whoever the
            // test is acting as. A test about the boundary itself says so.
            'visibility' => Post::VISIBILITY_PUBLIC,
            'likes_count' => 0,
            'comments_count' => 0,
            'shares_count' => 0,
        ];
    }

    /**
     * Indicate who wrote the post.
     */
    public function by(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
