<?php

namespace Database\Seeders;

use App\Models\MeditationCategory;
use App\Models\MeditationItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MeditationSeeder extends Seeder
{
    /**
     * Session titles and lengths, keyed by category label.
     *
     * @var array<string, list<array{title: string, duration_minutes: int}>>
     */
    protected array $meditations = [
        'Sleep' => [
            ['title' => 'Body Scan for Deep Rest', 'duration_minutes' => 22],
            ['title' => 'Letting Go of the Day', 'duration_minutes' => 14],
            ['title' => 'Rain on a Quiet Roof', 'duration_minutes' => 45],
        ],
        'Anxiety Relief' => [
            ['title' => 'Five Senses Grounding', 'duration_minutes' => 8],
            ['title' => 'Naming the Feeling', 'duration_minutes' => 12],
        ],
        'Focus' => [
            ['title' => 'Ten Minutes Before Deep Work', 'duration_minutes' => 10],
            ['title' => 'Returning to the Breath', 'duration_minutes' => 6],
        ],
        'Morning Wake-Up' => [
            ['title' => 'Setting an Intention', 'duration_minutes' => 7],
            ['title' => 'Sunrise Stretch and Breathe', 'duration_minutes' => 11],
        ],
        'Breathwork' => [
            ['title' => 'Box Breathing Basics', 'duration_minutes' => 5],
            ['title' => 'Extended Exhale Practice', 'duration_minutes' => 9],
        ],
        'Self-Compassion' => [
            ['title' => 'Loving-Kindness for a Hard Day', 'duration_minutes' => 15],
        ],
        'Walking Meditation' => [
            ['title' => 'Awareness on the Move', 'duration_minutes' => 18],
        ],
        'Gratitude' => [
            ['title' => 'Three Good Things', 'duration_minutes' => 9],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = MeditationCategory::query()->pluck('id', 'label');

        foreach ($this->meditations as $categoryLabel => $sessions) {
            $categoryId = $categories[$categoryLabel] ?? null;

            if ($categoryId === null) {
                continue;
            }

            foreach ($sessions as $session) {
                $slug = Str::slug($session['title']);

                MeditationItem::updateOrCreate(
                    ['category_id' => $categoryId, 'title' => $session['title']],
                    [
                        'instructions' => null,
                        'thumbnail' => "thumbnails/{$slug}.jpg",
                        'audio_url' => "https://cdn.winly.test/audio/{$slug}.mp3",
                        'duration_minutes' => $session['duration_minutes'],
                    ],
                );
            }
        }
    }
}
