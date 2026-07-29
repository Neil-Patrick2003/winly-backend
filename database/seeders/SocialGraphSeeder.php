<?php

namespace Database\Seeders;

use App\Models\Circle;
use App\Models\CircleMembership;
use App\Models\Comment;
use App\Models\Follow;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\Notification;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\Story;
use App\Models\StoryReaction;
use App\Models\StoryView;
use App\Models\User;
use App\Models\WinLearning;
use App\Models\WinMeditation;
use App\Models\WinMovement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Fills the feed side of the schema with a small, fully connected sample:
 * a handful of users who all follow each other, share each kind of win,
 * and react to each other's stories.
 */
class SocialGraphSeeder extends Seeder
{
    /**
     * How many extra users to stand up alongside the seeded test account.
     */
    protected const USER_COUNT = 5;

    /**
     * How many days of habit history to log.
     */
    protected const HABIT_HISTORY_DAYS = 7;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = $this->users();

        $this->seedFollows($users);
        $this->seedCircles($users);

        $posts = $this->seedWins($users);

        $this->seedEngagement($users, $posts);
        $this->seedStories($users);
        $this->seedHabits($users);
    }

    /**
     * The cast of the sample feed.
     *
     * @return Collection<int, User>
     */
    protected function users(): Collection
    {
        $existing = User::query()->orderBy('created_at')->get();

        $missing = max(0, self::USER_COUNT + 1 - $existing->count());

        if ($missing > 0) {
            $existing = $existing->concat(User::factory($missing)->create());
        }

        return $existing->take(self::USER_COUNT + 1)->values();
    }

    /**
     * Wire every user up to every other user.
     *
     * @param  Collection<int, User>  $users
     */
    protected function seedFollows(Collection $users): void
    {
        foreach ($users as $follower) {
            foreach ($users as $followee) {
                if ($follower->is($followee)) {
                    continue;
                }

                Follow::firstOrCreate([
                    'follower_id' => $follower->id,
                    'followee_id' => $followee->id,
                ]);
            }
        }

        foreach ($users as $user) {
            $user->forceFill([
                'followers_count' => $user->followers()->count(),
                'following_count' => $user->following()->count(),
            ])->save();
        }
    }

    /**
     * Stand up a few circles and drop everyone into two of them.
     *
     * @param  Collection<int, User>  $users
     */
    protected function seedCircles(Collection $users): void
    {
        $circles = Circle::query()->count() > 0
            ? Circle::query()->get()
            : Circle::factory(4)->create();

        foreach ($users as $index => $user) {
            foreach ([$index % $circles->count(), ($index + 1) % $circles->count()] as $offset) {
                CircleMembership::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'circle_id' => $circles[$offset]->id,
                    ],
                    ['joined_at' => now()->subDays($index)],
                );
            }
        }

        foreach ($circles as $circle) {
            $circle->forceFill(['members_count' => $circle->memberships()->count()])->save();
        }
    }

    /**
     * Give every user one post of each kind, plus one that carries all three.
     *
     * @param  Collection<int, User>  $users
     * @return Collection<int, Post>
     */
    protected function seedWins(Collection $users): Collection
    {
        $posts = collect();

        foreach ($users as $user) {
            $combinedPost = Post::factory()->by($user)->create([
                'caption' => 'Got all three in before lunch.',
            ]);

            WinMeditation::factory()->create([
                'post_id' => $combinedPost->id,
                'duration_minutes' => 10,
                'completed_at' => now()->subHours(2),
            ]);

            WinLearning::factory()->create([
                'post_id' => $combinedPost->id,
                'completed_at' => now()->subHours(2),
            ]);

            WinMovement::factory()->create([
                'post_id' => $combinedPost->id,
                'movement_type' => 'walk',
                'completed_at' => now()->subHours(2),
            ]);

            $meditationPost = Post::factory()->by($user)->create([
                'caption' => 'Sat with it for a bit this morning.',
            ]);

            WinMeditation::factory()->create([
                'post_id' => $meditationPost->id,
                'duration_minutes' => fake()->randomElement([5, 10, 15, 20]),
                'completed_at' => now()->subHours(3),
            ]);

            $learningPost = Post::factory()->by($user)->create([
                'caption' => 'Read something that reframed the whole week.',
            ]);

            WinLearning::factory()->create([
                'post_id' => $learningPost->id,
                'completed_at' => now()->subHours(6),
            ]);

            $movementPost = Post::factory()->by($user)->create([
                'caption' => 'Got the body moving before the inbox did.',
            ]);

            $movementWin = WinMovement::factory()->create([
                'post_id' => $movementPost->id,
                'media_attached' => true,
                'completed_at' => now()->subHours(9),
            ]);

            // A photo and a clip, to exercise a win carrying several files.
            $movementWin->media()->createMany([
                [
                    'post_id' => $movementPost->id,
                    'url' => 'https://cdn.winly.test/media/'.fake()->unique()->slug(3).'.jpg',
                    'kind' => 'image',
                    'position' => 0,
                ],
                [
                    'post_id' => $movementPost->id,
                    'url' => 'https://cdn.winly.test/media/'.fake()->unique()->slug(3).'.mp4',
                    'kind' => 'video',
                    'position' => 1,
                ],
            ]);

            $posts = $posts->concat([$combinedPost, $meditationPost, $learningPost, $movementPost]);

            // wins_count counts wins, not posts: the combined post holds three.
            $user->forceFill(['wins_count' => 6])->save();
        }

        return $posts;
    }

    /**
     * Have everyone like and comment on everyone else's wins.
     *
     * @param  Collection<int, User>  $users
     * @param  Collection<int, Post>  $posts
     */
    protected function seedEngagement(Collection $users, Collection $posts): void
    {
        foreach ($posts as $post) {
            $others = $users->reject(fn (User $user): bool => $user->id === $post->user_id);

            foreach ($others->take(3) as $user) {
                PostLike::firstOrCreate([
                    'post_id' => $post->id,
                    'user_id' => $user->id,
                ]);

                Notification::create([
                    'user_id' => $post->user_id,
                    'actor_id' => $user->id,
                    'type' => 'like',
                    'post_id' => $post->id,
                    'message' => "{$user->full_name} celebrated your win.",
                ]);
            }

            foreach ($others->take(2) as $user) {
                Comment::factory()->create([
                    'post_id' => $post->id,
                    'user_id' => $user->id,
                ]);
            }

            $post->forceFill([
                'likes_count' => $post->likes()->count(),
                'comments_count' => $post->comments()->count(),
            ])->save();
        }
    }

    /**
     * Give every user a live story and have the others see it.
     *
     * @param  Collection<int, User>  $users
     */
    protected function seedStories(Collection $users): void
    {
        foreach ($users as $user) {
            $story = Story::factory()->create(['user_id' => $user->id]);

            foreach ($users->reject(fn (User $viewer): bool => $viewer->is($user)) as $viewer) {
                StoryView::firstOrCreate(
                    ['story_id' => $story->id, 'viewer_id' => $viewer->id],
                    ['viewed_at' => now()->subMinutes(30)],
                );
            }

            foreach ($users->reject(fn (User $reactor): bool => $reactor->is($user))->take(2) as $reactor) {
                StoryReaction::firstOrCreate(
                    ['story_id' => $story->id, 'user_id' => $reactor->id],
                    ['reaction_type' => 'celebrate'],
                );
            }
        }
    }

    /**
     * Track two habits per user with a week of history behind them.
     *
     * @param  Collection<int, User>  $users
     */
    protected function seedHabits(Collection $users): void
    {
        foreach ($users as $user) {
            foreach (['water', 'meditation'] as $type) {
                $habit = Habit::factory()->ofType($type)->create(['user_id' => $user->id]);

                for ($day = 0; $day < self::HABIT_HISTORY_DAYS; $day++) {
                    $date = now()->subDays($day);

                    HabitLog::firstOrCreate(
                        ['habit_id' => $habit->id, 'date' => $date->toDateString()],
                        [
                            'user_id' => $user->id,
                            'value_logged' => fake()->numberBetween(1, $habit->daily_goal),
                            'completed' => $day % 3 !== 0,
                            'logged_at' => $date->copy()->setTime(20, 0),
                        ],
                    );
                }
            }
        }
    }
}
