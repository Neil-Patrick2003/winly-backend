<?php

use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityMembership;
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
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

test('every model is keyed by a uuid', function (string $model) {
    $record = $model::factory()->create();

    expect($record->getKey())->toBeString();
    expect(Str::isUuid($record->getKey()))->toBeTrue();
})->with([
    User::class,
    Community::class,
    CommunityMembership::class,
    Follow::class,
    Post::class,
    PostLike::class,
    Comment::class,
    WinMeditation::class,
    WinLearning::class,
    WinMovement::class,
    Story::class,
    StoryView::class,
    StoryReaction::class,
    Habit::class,
    HabitLog::class,
    Notification::class,
]);

test('following is directional', function () {
    $neil = User::factory()->create();
    $ada = User::factory()->create();

    Follow::factory()->from($neil)->to($ada)->create();

    expect($neil->following()->pluck('users.id')->all())->toBe([$ada->id]);
    expect($neil->followers()->count())->toBe(0);
    expect($ada->followers()->pluck('users.id')->all())->toBe([$neil->id]);
    expect($ada->following()->count())->toBe(0);
});

test('a user cannot follow the same person twice', function () {
    $neil = User::factory()->create();
    $ada = User::factory()->create();

    Follow::factory()->from($neil)->to($ada)->create();
    Follow::factory()->from($neil)->to($ada)->create();
})->throws(UniqueConstraintViolationException::class);

test('joining a community is recorded once per user', function () {
    $user = User::factory()->create();
    $community = Community::factory()->create();

    CommunityMembership::factory()->create([
        'user_id' => $user->id,
        'community_id' => $community->id,
    ]);

    expect($user->communities()->pluck('communities.id')->all())->toBe([$community->id]);
    expect($community->members()->pluck('users.id')->all())->toBe([$user->id]);

    CommunityMembership::factory()->create([
        'user_id' => $user->id,
        'community_id' => $community->id,
    ]);
})->throws(UniqueConstraintViolationException::class);

test('a post carries exactly one kind of win', function () {
    $meditationPost = Post::factory()->create();
    WinMeditation::factory()->create(['post_id' => $meditationPost->id]);

    $learningPost = Post::factory()->create();
    WinLearning::factory()->create(['post_id' => $learningPost->id]);

    $movementPost = Post::factory()->create();
    WinMovement::factory()->create(['post_id' => $movementPost->id, 'movement_type' => 'run']);

    expect($meditationPost->winMeditation)->not->toBeNull();
    expect($meditationPost->winLearning)->toBeNull();
    expect($learningPost->winLearning->learned_text)->toBeString();
    expect($movementPost->winMovement->movement_type)->toBe('run');
});

test('a meditation win records the timer and whether it was seen through', function () {
    $finished = WinMeditation::factory()->create(['duration_minutes' => 20]);
    $bailed = WinMeditation::factory()->cutShort()->create(['duration_minutes' => 3]);

    expect($finished->duration_minutes)->toBe(20);
    expect($finished->completed)->toBeTrue();
    expect($bailed->duration_minutes)->toBe(3);
    expect($bailed->completed)->toBeFalse();
});

test('a user can only like a post once', function () {
    $post = Post::factory()->create();
    $user = User::factory()->create();

    PostLike::factory()->create(['post_id' => $post->id, 'user_id' => $user->id]);

    expect($post->likes()->count())->toBe(1);
    expect($user->postLikes()->count())->toBe(1);

    PostLike::factory()->create(['post_id' => $post->id, 'user_id' => $user->id]);
})->throws(UniqueConstraintViolationException::class);

test('deleting a post takes its likes, comments and win detail with it', function () {
    $post = Post::factory()->create();

    PostLike::factory()->create(['post_id' => $post->id]);
    Comment::factory()->create(['post_id' => $post->id]);
    WinMovement::factory()->create(['post_id' => $post->id]);

    $post->delete();

    expect(PostLike::count())->toBe(0);
    expect(Comment::count())->toBe(0);
    expect(WinMovement::count())->toBe(0);
});

test('deleting a user takes their posts and habits with them', function () {
    $user = User::factory()->create();

    Post::factory()->by($user)->create();
    HabitLog::factory()->create([
        'habit_id' => Habit::factory()->create(['user_id' => $user->id]),
        'user_id' => $user->id,
    ]);

    $user->forceDelete();

    expect(Post::count())->toBe(0);
    expect(Habit::count())->toBe(0);
    expect(HabitLog::count())->toBe(0);
});

test('stories are only active until they expire', function () {
    $live = Story::factory()->create();
    $gone = Story::factory()->expired()->create();

    expect(Story::active()->pluck('id')->all())->toBe([$live->id]);
    expect(Story::expired()->pluck('id')->all())->toBe([$gone->id]);
});

test('a viewer is only counted once per story', function () {
    $story = Story::factory()->create();
    $viewer = User::factory()->create();

    StoryView::factory()->create(['story_id' => $story->id, 'viewer_id' => $viewer->id]);

    expect($story->views()->count())->toBe(1);

    StoryView::factory()->create(['story_id' => $story->id, 'viewer_id' => $viewer->id]);
})->throws(UniqueConstraintViolationException::class);

test('a user holds a single reaction per story', function () {
    $story = Story::factory()->create();
    $user = User::factory()->create();

    StoryReaction::factory()->create([
        'story_id' => $story->id,
        'user_id' => $user->id,
        'reaction_type' => 'love',
    ]);

    expect($story->reactions()->sole()->reaction_type)->toBe('love');

    StoryReaction::factory()->create(['story_id' => $story->id, 'user_id' => $user->id]);
})->throws(UniqueConstraintViolationException::class);

test('a habit is logged at most once a day', function () {
    $habit = Habit::factory()->create();
    $today = now()->toDateString();

    HabitLog::factory()->on($today)->create(['habit_id' => $habit->id, 'user_id' => $habit->user_id]);

    expect($habit->logs()->count())->toBe(1);

    HabitLog::factory()->on($today)->create(['habit_id' => $habit->id, 'user_id' => $habit->user_id]);
})->throws(UniqueConstraintViolationException::class);

test('habit logs can be pulled for a date range', function () {
    $habit = Habit::factory()->create();

    foreach ([0, 3, 10] as $daysAgo) {
        HabitLog::factory()
            ->on(now()->subDays($daysAgo)->toDateString())
            ->create(['habit_id' => $habit->id, 'user_id' => $habit->user_id]);
    }

    $recent = HabitLog::loggedBetween(now()->subDays(7)->toDateString(), now()->toDateString())->count();

    expect($recent)->toBe(2);
});

test('a user only tracks one habit of each type', function () {
    $user = User::factory()->create();

    Habit::factory()->ofType('water')->create(['user_id' => $user->id]);
    Habit::factory()->ofType('water')->create(['user_id' => $user->id]);
})->throws(UniqueConstraintViolationException::class);

test('notifications separate read from unread', function () {
    $user = User::factory()->create();

    Notification::factory()->count(2)->create(['user_id' => $user->id]);
    Notification::factory()->read()->create(['user_id' => $user->id]);

    expect($user->notifications()->count())->toBe(3);
    expect(Notification::unread()->count())->toBe(2);
});

test('a notification survives its actor and post going away', function () {
    $post = Post::factory()->create();
    $actor = User::factory()->create();

    $notification = Notification::factory()->create([
        'actor_id' => $actor->id,
        'post_id' => $post->id,
    ]);

    $post->delete();
    $actor->forceDelete();

    expect($notification->fresh())
        ->not->toBeNull()
        ->actor_id->toBeNull()
        ->post_id->toBeNull();
});
