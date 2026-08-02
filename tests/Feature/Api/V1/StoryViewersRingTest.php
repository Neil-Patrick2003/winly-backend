<?php

use App\Models\Story;
use App\Models\StoryView;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * The ring around your own story.
 *
 * It used to be lit for as long as the story was up, which told you something
 * you already knew. It is lit now when there is something you have not caught
 * up on — a story just posted, or somebody watching since you last looked —
 * and dark once you have opened the viewer list.
 */
beforeEach(function () {
    $this->author = User::factory()->create();
    $this->watcher = User::factory()->create();
    Sanctum::actingAs($this->author);
});

function storyFor(User $author): Story
{
    return Story::factory()->create([
        'user_id' => $author->id,
        'expires_at' => now()->addHours(Story::LIFETIME_HOURS),
    ]);
}

test('a story just posted lights the ring before anybody has watched', function () {
    storyFor($this->author);

    // Nothing has viewed it, and it is still new to its author: they have not
    // opened the viewer list on it even once.
    $this->getJson(route('api.v1.user'))
        ->assertOk()
        ->assertJsonPath('data.has_active_story', true)
        ->assertJsonPath('data.has_new_story_activity', true);
});

test('a story whose viewers have been checked goes dark with nobody watching', function () {
    $story = storyFor($this->author);

    $this->getJson(route('api.v1.stories.viewers', $story))->assertOk();

    $this->getJson(route('api.v1.user'))
        ->assertOk()
        ->assertJsonPath('data.has_new_story_activity', false);
});

test('watching your own story puts the ring out, with nobody having viewed it', function () {
    $story = storyFor($this->author);

    $this->getJson(route('api.v1.user'))->assertJsonPath('data.has_new_story_activity', true);

    // The ordinary way of catching up: tapping your own bubble and watching it.
    // No viewer list involved, and nobody else has been anywhere near it.
    $this->postJson(route('api.v1.stories.view', $story))->assertOk();

    expect($story->fresh()->viewers_checked_at)->not->toBeNull()
        ->and($story->views()->count())->toBe(0);

    $this->getJson(route('api.v1.user'))
        ->assertOk()
        ->assertJsonPath('data.has_new_story_activity', false);
});

test('watching your own story is still not a view of it', function () {
    $story = storyFor($this->author);

    $this->postJson(route('api.v1.stories.view', $story))
        ->assertOk()
        ->assertJsonPath('data.views_count', 0);

    expect($story->views()->count())->toBe(0);
});

test('somebody watching after you caught up lights it again', function () {
    $story = storyFor($this->author);

    $this->postJson(route('api.v1.stories.view', $story))->assertOk();
    $this->getJson(route('api.v1.user'))->assertJsonPath('data.has_new_story_activity', false);

    StoryView::create([
        'story_id' => $story->id,
        'viewer_id' => $this->watcher->id,
        'viewed_at' => now()->addMinute(),
    ]);

    $this->getJson(route('api.v1.user'))
        ->assertOk()
        ->assertJsonPath('data.has_new_story_activity', true);
});

test('the first watcher lights it again after it was checked', function () {
    $story = storyFor($this->author);

    // Checked while empty, so the ring is dark before anybody arrives.
    $this->getJson(route('api.v1.stories.viewers', $story))->assertOk();
    $this->getJson(route('api.v1.user'))->assertJsonPath('data.has_new_story_activity', false);

    StoryView::create([
        'story_id' => $story->id,
        'viewer_id' => $this->watcher->id,
        'viewed_at' => now()->addMinute(),
    ]);

    $this->getJson(route('api.v1.user'))
        ->assertOk()
        ->assertJsonPath('data.has_new_story_activity', true);
});

test('looking at who watched puts it out', function () {
    $story = storyFor($this->author);

    StoryView::create([
        'story_id' => $story->id,
        'viewer_id' => $this->watcher->id,
        'viewed_at' => now(),
    ]);

    $this->getJson(route('api.v1.stories.viewers', $story))->assertOk();

    expect($story->fresh()->viewers_checked_at)->not->toBeNull();

    $this->getJson(route('api.v1.user'))
        ->assertOk()
        ->assertJsonPath('data.has_new_story_activity', false);
});

test('somebody watching after you looked lights it again', function () {
    $story = storyFor($this->author);

    StoryView::create([
        'story_id' => $story->id,
        'viewer_id' => $this->watcher->id,
        'viewed_at' => now(),
    ]);

    $this->getJson(route('api.v1.stories.viewers', $story))->assertOk();
    $this->getJson(route('api.v1.user'))->assertJsonPath('data.has_new_story_activity', false);

    StoryView::create([
        'story_id' => $story->id,
        'viewer_id' => User::factory()->create()->id,
        'viewed_at' => now()->addMinute(),
    ]);

    $this->getJson(route('api.v1.user'))
        ->assertOk()
        ->assertJsonPath('data.has_new_story_activity', true);
});

test('a watcher on an expired story does not light it', function () {
    $story = Story::factory()->create([
        'user_id' => $this->author->id,
        'expires_at' => now()->subHour(),
    ]);

    StoryView::create([
        'story_id' => $story->id,
        'viewer_id' => $this->watcher->id,
        'viewed_at' => now()->subHours(2),
    ]);

    // Nothing is up, so there is no ring for it to light.
    $this->getJson(route('api.v1.user'))
        ->assertOk()
        ->assertJsonPath('data.has_active_story', false)
        ->assertJsonPath('data.has_new_story_activity', false);
});

test('each story is measured from its own last look', function () {
    $checked = storyFor($this->author);
    $unchecked = storyFor($this->author);

    foreach ([$checked, $unchecked] as $story) {
        StoryView::create([
            'story_id' => $story->id,
            'viewer_id' => $this->watcher->id,
            'viewed_at' => now(),
        ]);
    }

    $this->getJson(route('api.v1.stories.viewers', $checked))->assertOk();

    // One settled, the other never opened — so there is still something new.
    $this->getJson(route('api.v1.user'))
        ->assertOk()
        ->assertJsonPath('data.has_new_story_activity', true);

    $this->getJson(route('api.v1.stories.viewers', $unchecked))->assertOk();

    $this->getJson(route('api.v1.user'))
        ->assertOk()
        ->assertJsonPath('data.has_new_story_activity', false);
});
