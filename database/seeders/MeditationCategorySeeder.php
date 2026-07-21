<?php

namespace Database\Seeders;

use App\Models\MeditationCategory;
use Illuminate\Database\Seeder;

class MeditationCategorySeeder extends Seeder
{
    /**
     * The starting set of categories.
     *
     * @var list<array{name: string, icon: string, description: string}>
     */
    protected array $categories = [
        [
            'name' => 'Sleep',
            'icon' => 'moon',
            'description' => 'Wind-down sessions and body scans that carry you into deep rest.',
        ],
        [
            'name' => 'Anxiety Relief',
            'icon' => 'waves',
            'description' => 'Grounding practices for racing thoughts and a tight chest.',
        ],
        [
            'name' => 'Focus',
            'icon' => 'brain',
            'description' => 'Short attention-training sessions to settle in before deep work.',
        ],
        [
            'name' => 'Morning Wake-Up',
            'icon' => 'sunrise',
            'description' => 'Gentle openers that set an intention for the day ahead.',
        ],
        [
            'name' => 'Breathwork',
            'icon' => 'wind',
            'description' => 'Paced breathing patterns, from box breathing to extended exhales.',
        ],
        [
            'name' => 'Self-Compassion',
            'icon' => 'heart-pulse',
            'description' => 'Loving-kindness practices for the days you are hardest on yourself.',
        ],
        [
            'name' => 'Walking Meditation',
            'icon' => 'mountain-snow',
            'description' => 'Movement-based awareness you can practise outdoors.',
        ],
        [
            'name' => 'Gratitude',
            'icon' => 'sparkles',
            'description' => 'Reflective sessions that widen your attention to what is already good.',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->categories as $category) {
            MeditationCategory::updateOrCreate(
                ['name' => $category['name']],
                $category,
            );
        }
    }
}
