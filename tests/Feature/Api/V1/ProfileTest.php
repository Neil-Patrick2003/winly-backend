<?php

use App\Models\Follow;
use App\Models\Post;
use App\Models\Story;
use App\Models\User;
use App\Models\WinLearning;
use App\Models\WinMeditation;
use App\Models\WinMovement;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->other = User::factory()->create();
    Sanctum::actingAs($this->user);
});

test('guests cannot read a profile', function () {
    app()['auth']->forgetGuards();

    $this->getJson(route('api.v1.profile.show'))->assertUnauthorized();
    $this->getJson(route('api.v1.users.show', $this->other))->assertUnauthorized();
});

test('a user can read their own profile', function () {
    $this->getJson(route('api.v1.profile.show'))
        ->assertOk()
        ->assertJsonPath('data.id', $this->user->id)
        ->assertJsonPath('data.is_self', true)
        ->assertJsonPath('data.email', $this->user->email)
        ->assertJsonStructure(['data' => [
            'id', 'full_name', 'username', 'avatar_url', 'bio', 'cover_gradient',
            'wins_count', 'followers_count', 'following_count',
            'streak_days', 'longest_streak', 'last_win_on',
            'is_private', 'is_self', 'is_following', 'follows_you',
            'has_active_story', 'email', 'email_verified_at', 'is_admin',
            'last_active_at', 'created_at', 'updated_at',
        ]]);
});

test('the same profile is reachable by id', function () {
    $this->getJson(route('api.v1.users.show', $this->user))
        ->assertOk()
        ->assertJsonPath('data.id', $this->user->id)
        ->assertJsonPath('data.is_self', true);
});

test('somebody elses profile does not leak their private details', function () {
    $this->getJson(route('api.v1.users.show', $this->other))
        ->assertOk()
        ->assertJsonPath('data.id', $this->other->id)
        ->assertJsonPath('data.is_self', false)
        ->assertJsonPath('data.full_name', $this->other->full_name)
        ->assertJsonPath('data.bio', $this->other->bio)
        ->assertJsonMissingPath('data.email')
        ->assertJsonMissingPath('data.email_verified_at')
        ->assertJsonMissingPath('data.is_admin')
        ->assertJsonMissingPath('data.last_active_at');
});

test('the profile carries the wins, follower and following counts', function () {
    $this->other->forceFill([
        'wins_count' => 42,
        'followers_count' => 9,
        'following_count' => 3,
    ])->save();

    $this->getJson(route('api.v1.users.show', $this->other))
        ->assertOk()
        ->assertJsonPath('data.wins_count', 42)
        ->assertJsonPath('data.followers_count', 9)
        ->assertJsonPath('data.following_count', 3);
});

test('the counts a profile shows follow real follows', function () {
    $this->postJson(route('api.v1.users.follow', $this->other))->assertCreated();

    $this->getJson(route('api.v1.users.show', $this->other))
        ->assertOk()
        ->assertJsonPath('data.followers_count', 1);

    $this->getJson(route('api.v1.profile.show'))
        ->assertOk()
        ->assertJsonPath('data.following_count', 1);
});

test('the profile says whether the reader follows them', function () {
    $this->getJson(route('api.v1.users.show', $this->other))
        ->assertOk()
        ->assertJsonPath('data.is_following', false);

    $this->postJson(route('api.v1.users.follow', $this->other))->assertCreated();

    $this->getJson(route('api.v1.users.show', $this->other))
        ->assertOk()
        ->assertJsonPath('data.is_following', true);
});

test('the profile says whether they follow the reader back', function () {
    Follow::factory()->from($this->other)->to($this->user)->create();

    $this->getJson(route('api.v1.users.show', $this->other))
        ->assertOk()
        ->assertJsonPath('data.follows_you', true)
        ->assertJsonPath('data.is_following', false);
});

test('one persons follow does not colour anothers profile', function () {
    $bystander = User::factory()->create();

    Follow::factory()->from($bystander)->to($this->other)->create();

    $this->getJson(route('api.v1.users.show', $this->other))
        ->assertOk()
        ->assertJsonPath('data.is_following', false)
        ->assertJsonPath('data.follows_you', false);
});

test('a live streak is reported as it stands', function () {
    $this->user->forceFill([
        'streak_days' => 7,
        'longest_streak' => 12,
        'last_win_on' => today(),
    ])->save();

    $this->getJson(route('api.v1.profile.show'))
        ->assertOk()
        ->assertJsonPath('data.streak_days', 7)
        ->assertJsonPath('data.longest_streak', 12)
        ->assertJsonPath('data.last_win_on', today()->toDateString());
});

test('a win yesterday still counts as a live streak', function () {
    $this->user->forceFill([
        'streak_days' => 4,
        'longest_streak' => 4,
        'last_win_on' => today()->subDay(),
    ])->save();

    $this->getJson(route('api.v1.profile.show'))
        ->assertOk()
        ->assertJsonPath('data.streak_days', 4);
});

test('a lapsed streak reads as zero while the longest survives', function () {
    $this->user->forceFill([
        'streak_days' => 7,
        'longest_streak' => 12,
        'last_win_on' => today()->subDays(3),
    ])->save();

    $this->getJson(route('api.v1.profile.show'))
        ->assertOk()
        ->assertJsonPath('data.streak_days', 0)
        ->assertJsonPath('data.longest_streak', 12);
});

test('someone who has never won has no streak at all', function () {
    $this->getJson(route('api.v1.profile.show'))
        ->assertOk()
        ->assertJsonPath('data.streak_days', 0)
        ->assertJsonPath('data.longest_streak', 0)
        ->assertJsonPath('data.last_win_on', null);
});

test('the signed-in user endpoint agrees with the profile about the streak', function () {
    $this->user->forceFill([
        'streak_days' => 5,
        'longest_streak' => 5,
        'last_win_on' => today()->subWeek(),
    ])->save();

    $this->getJson(route('api.v1.user'))
        ->assertOk()
        ->assertJsonPath('data.streak_days', 0);

    $this->getJson(route('api.v1.profile.show'))
        ->assertOk()
        ->assertJsonPath('data.streak_days', 0);
});

test('the profile flags a running story', function () {
    $this->getJson(route('api.v1.users.show', $this->other))
        ->assertOk()
        ->assertJsonPath('data.has_active_story', false);

    Story::factory()->create(['user_id' => $this->other->id]);

    $this->getJson(route('api.v1.users.show', $this->other))
        ->assertOk()
        ->assertJsonPath('data.has_active_story', true);
});

test('a private account still has a readable profile header', function () {
    $private = User::factory()->private()->create();

    $this->getJson(route('api.v1.users.show', $private))
        ->assertOk()
        ->assertJsonPath('data.is_private', true)
        ->assertJsonPath('data.full_name', $private->full_name);
});

test('an unknown user is a 404', function () {
    $this->getJson(route('api.v1.users.show', '019fa2bf-0000-7000-8000-000000000000'))
        ->assertNotFound();
});

test('a deleted user is a 404', function () {
    $this->other->delete();

    $this->getJson(route('api.v1.users.show', $this->other))->assertNotFound();
});

test('a profile counts posts and wins separately', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    // One post carrying all three pillars: three wins, one post.
    $post = Post::factory()->for($user)->create();
    WinMeditation::factory()->for($post, 'post')->create();
    WinLearning::factory()->for($post, 'post')->create();
    WinMovement::factory()->for($post, 'post')->create();
    $user->forceFill(['wins_count' => 3])->save();

    $this->getJson(route('api.v1.users.show', $user))
        ->assertOk()
        ->assertJsonPath('data.posts_count', 1)
        ->assertJsonPath('data.wins_count', 3);

    $this->getJson(route('api.v1.user'))
        ->assertOk()
        ->assertJsonPath('data.posts_count', 1);
});
