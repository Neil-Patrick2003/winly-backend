<?php

namespace Database\Factories;

use App\Models\WinMedia;
use App\Models\WinMovement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<WinMedia>
 */
class WinMediaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * A file cannot exist without a win, so one is stood up by default. Pass
     * `forWin()` when you already have the win you want it hung off.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $win = WinMovement::factory()->create();

        return [
            'post_id' => $win->post_id,
            'win_type' => $win->getMorphClass(),
            'win_id' => $win->id,
            'url' => 'https://cdn.winly.test/media/'.fake()->unique()->slug(3).'.jpg',
            'kind' => 'image',
            'position' => 0,
        ];
    }

    /**
     * Hang the file off an existing win.
     */
    public function forWin(Model $win): static
    {
        return $this->state(fn (array $attributes) => [
            'post_id' => $win->post_id,
            'win_type' => $win->getMorphClass(),
            'win_id' => $win->getKey(),
        ]);
    }

    /**
     * Indicate that the file is a clip rather than a photo.
     */
    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'url' => 'https://cdn.winly.test/media/'.fake()->unique()->slug(3).'.mp4',
            'kind' => 'video',
        ]);
    }
}
