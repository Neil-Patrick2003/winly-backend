<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporter_id' => User::factory(),
            'reportable_type' => (new Post)->getMorphClass(),
            'reportable_id' => Post::factory(),
            'reason' => fake()->randomElement(Report::REASONS),
            'note' => null,
            'status' => Report::STATUS_PENDING,
        ];
    }

    /**
     * A report about something other than a post.
     */
    public function about(object $reportable): static
    {
        return $this->state(fn (): array => [
            'reportable_type' => $reportable->getMorphClass(),
            'reportable_id' => $reportable->getKey(),
        ]);
    }

    /**
     * One staff have already dealt with.
     */
    public function reviewed(string $status = Report::STATUS_ACTIONED): static
    {
        return $this->state(fn (): array => [
            'status' => $status,
            'reviewed_at' => now(),
        ]);
    }
}
