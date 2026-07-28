<?php

namespace Database\Seeders;

use App\Models\MeditationCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MeditationCategorySeeder extends Seeder
{
    /**
     * The starting set of categories.
     *
     * @var list<array{label: string, icon: string, description: string}>
     */
    protected array $categories = [
        [
            'label' => 'Sleep',
            'icon' => 'moon',
            'description' => 'Wind-down sessions and body scans that carry you into deep rest.',
        ],
        [
            'label' => 'Anxiety Relief',
            'icon' => 'waves',
            'description' => 'Grounding practices for racing thoughts and a tight chest.',
        ],
        [
            'label' => 'Focus',
            'icon' => 'brain',
            'description' => 'Short attention-training sessions to settle in before deep work.',
        ],
        [
            'label' => 'Morning Wake-Up',
            'icon' => 'sunrise',
            'description' => 'Gentle openers that set an intention for the day ahead.',
        ],
        [
            'label' => 'Breathwork',
            'icon' => 'wind',
            'description' => 'Paced breathing patterns, from box breathing to extended exhales.',
        ],
        [
            'label' => 'Self-Compassion',
            'icon' => 'heart-pulse',
            'description' => 'Loving-kindness practices for the days you are hardest on yourself.',
        ],
        [
            'label' => 'Walking Meditation',
            'icon' => 'mountain-snow',
            'description' => 'Movement-based awareness you can practise outdoors.',
        ],
        [
            'label' => 'Gratitude',
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
                ['label' => $category['label']],
                [...$category, 'slug' => Str::slug($category['label'])],
            );
        }
    }
}
