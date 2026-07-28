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
            'image_url' => null,
            'likes_count' => 0,
            'comments_count' => 0,
            'shares_count' => 0,
        ];
    }

    /**
     * Indicate that the post carries a photo.
     */
    public function withImage(): static
    {
        return $this->state(fn (array $attributes) => [
            'image_url' => 'https://cdn.winly.test/posts/'.fake()->unique()->slug(3).'.jpg',
        ]);
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
