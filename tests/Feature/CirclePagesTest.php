<?php

use App\Models\Circle;
use App\Models\CircleMembership;
use App\Models\Post;
use App\Models\User;
use App\Models\WinLearning;
use App\Models\WinMeditation;
use App\Models\WinMovement;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->owner = User::factory()->create(['full_name' => 'Ada Owner']);
    $this->member = User::factory()->create(['full_name' => 'Bea Member']);

    $this->circle = Circle::factory()->create([
        'owner_id' => $this->owner->id,
        'name' => 'Morning Sitters',
    ]);

    CircleMembership::create([
        'user_id' => $this->owner->id,
        'circle_id' => $this->circle->id,
        'joined_at' => now()->subDays(2),
    ]);
    CircleMembership::create([
        'user_id' => $this->member->id,
        'circle_id' => $this->circle->id,
        'joined_at' => now()->subDay(),
    ]);

    $this->circle->update(['members_count' => 2]);
});

test('guests are sent to login', function () {
    $this->get(route('circles.members', $this->circle))->assertRedirect(route('login'));
});

test('the members tab lists who is in the circle', function () {
    $this->actingAs($this->member)
        ->get(route('circles.members', $this->circle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('circles/members')
            ->where('circle.name', 'Morning Sitters')
            ->where('circle.can_manage', false)
            ->has('members.data', 2)
            ->where('members.data.0.full_name', 'Ada Owner')
            ->where('members.data.0.is_owner', true)
        );
});

test('the owner sees the circle as manageable', function () {
    $this->actingAs($this->owner)
        ->get(route('circles.members', $this->circle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('circle.can_manage', true));
});

test('the posts tab lists only wins shared into this circle', function () {
    postInCircle($this->circle, $this->member, ['caption' => 'Sat for twenty.']);

    // Shared with everybody, and shared into somebody else's circle.
    Post::factory()->create(['user_id' => $this->member->id]);
    postInCircle(Circle::factory()->create(), $this->member);

    $this->actingAs($this->member)
        ->get(route('circles.posts', $this->circle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('circles/posts')
            ->has('posts.data', 1)
            ->where('posts.data.0.caption', 'Sat for twenty.')
            ->where('posts.data.0.author.full_name', 'Bea Member')
        );
});

/**
 * Share a win of the given kind into a circle, on the day it was done.
 */
function shareWin(Circle $circle, User $user, string $type, CarbonInterface $on): void
{
    $post = postInCircle($circle, $user, ['created_at' => $on]);

    $shared = ['post_id' => $post->id, 'completed_at' => $on];

    match ($type) {
        'meditation' => WinMeditation::factory()->create($shared),
        'learning' => WinLearning::factory()->create($shared),
        'movement' => WinMovement::factory()->create($shared),
    };
}

test('the tracker counts each kind of win a member has shared', function () {
    shareWin($this->circle, $this->member, 'meditation', today());
    shareWin($this->circle, $this->member, 'meditation', today()->subDays(2));
    shareWin($this->circle, $this->member, 'movement', today()->subDay());

    $this->actingAs($this->member)
        ->get(route('circles.tracker', $this->circle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('circles/tracker')
            ->where('winTypes', ['meditation', 'learning', 'movement'])
            ->where('days', 30)
            ->has('members.data', 2)
            ->where('members.data.1.full_name', 'Bea Member')
            ->where('members.data.1.wins.meditation', 2)
            ->where('members.data.1.wins.movement', 1)
            ->where('members.data.1.wins.learning', 0)
            ->where('members.data.1.total', 3)
        );
});

test('the total counts days logged, not wins stacked into one day', function () {
    // Four wins, three of them on the same day — two of those the same kind,
    // so both the per-table dedupe and the union across tables are exercised.
    shareWin($this->circle, $this->member, 'meditation', today()->setTime(7, 0));
    shareWin($this->circle, $this->member, 'meditation', today()->setTime(12, 15));
    shareWin($this->circle, $this->member, 'movement', today()->setTime(18, 30));
    shareWin($this->circle, $this->member, 'learning', today()->subDays(3));

    $this->actingAs($this->member)
        ->get(route('circles.tracker', $this->circle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('members.data.1.wins.meditation', 2)
            ->where('members.data.1.wins.movement', 1)
            ->where('members.data.1.wins.learning', 1)
            ->where('members.data.1.total', 2)
        );
});

test('total points counts every win, including several in one day', function () {
    // The same four wins as the days test above: three today, one earlier.
    shareWin($this->circle, $this->member, 'meditation', today()->setTime(7, 0));
    shareWin($this->circle, $this->member, 'meditation', today()->setTime(12, 15));
    shareWin($this->circle, $this->member, 'movement', today()->setTime(18, 30));
    shareWin($this->circle, $this->member, 'learning', today()->subDays(3));

    $this->actingAs($this->member)
        ->get(route('circles.tracker', $this->circle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            // Two days turned up, four wins done on them.
            ->where('members.data.1.total', 2)
            ->where('members.data.1.total_points', 4)
            // Nobody has shared anything, so their score is not blank.
            ->where('members.data.0.total_points', 0)
        );
});

test('points left outside the range are not scored', function () {
    shareWin($this->circle, $this->member, 'meditation', today());
    shareWin($this->circle, $this->member, 'movement', today()->subDays(20));

    $this->actingAs($this->member)
        ->get(route('circles.tracker', [
            'circle' => $this->circle,
            'from' => today()->subDays(6)->toDateString(),
            'to' => today()->toDateString(),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('members.data.1.total_points', 1));
});

test('days outside the range are not counted in the total', function () {
    shareWin($this->circle, $this->member, 'meditation', today());
    shareWin($this->circle, $this->member, 'movement', today()->subDays(20));

    $this->actingAs($this->member)
        ->get(route('circles.tracker', [
            'circle' => $this->circle,
            'from' => today()->subDays(6)->toDateString(),
            'to' => today()->toDateString(),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('members.data.1.total', 1));
});

test('a streak that has lapsed is not still shown as running', function () {
    /*
     * The columns as a win three weeks ago would have left them. `streak_days`
     * is the run ending at that win and keeps its number for good, so reading
     * it straight puts a one day streak against a member whose row is empty.
     */
    $this->member->forceFill([
        'streak_days' => 1,
        'longest_streak' => 4,
        'last_win_on' => today()->subWeeks(3),
    ])->save();

    $this->actingAs($this->member)
        ->get(route('circles.tracker', $this->circle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('members.data.1.streak_days', 0)
            ->where('members.data.1.total', 0)
            // The best run is a record of something that happened, so it stays.
            ->where('members.data.1.longest_streak', 4)
        );
});

test('the members tab does not show a lapsed streak either', function () {
    $this->member->forceFill([
        'streak_days' => 2,
        'last_win_on' => today()->subWeeks(3),
    ])->save();

    $this->actingAs($this->member)
        ->get(route('circles.members', $this->circle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('members.data.1.full_name', 'Bea Member')
            ->where('members.data.1.streak_days', 0)
        );
});

test('a kind nobody has done still gets a column, at zero', function () {
    $this->actingAs($this->member)
        ->get(route('circles.tracker', $this->circle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('winTypes', ['meditation', 'learning', 'movement'])
            ->where('members.data.0.wins.learning', 0)
            ->where('members.data.0.total', 0)
        );
});

test('the two dates decide what is counted', function () {
    shareWin($this->circle, $this->member, 'meditation', today());
    shareWin($this->circle, $this->member, 'meditation', today()->subDays(20));

    $this->actingAs($this->member)
        ->get(route('circles.tracker', [
            'circle' => $this->circle,
            'from' => today()->subDays(6)->toDateString(),
            'to' => today()->toDateString(),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('days', 7)
            ->where('members.data.1.wins.meditation', 1)
        );

    $this->actingAs($this->member)
        ->get(route('circles.tracker', [
            'circle' => $this->circle,
            'from' => today()->subDays(29)->toDateString(),
            'to' => today()->toDateString(),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('members.data.1.wins.meditation', 2));
});

test('both ends of the range are counted, not just what falls between', function () {
    $opened = today()->subDays(5);
    $closed = today()->subDays(3);

    // One win on each boundary, and one outside.
    shareWin($this->circle, $this->member, 'movement', $opened);
    shareWin($this->circle, $this->member, 'learning', $closed);
    shareWin($this->circle, $this->member, 'meditation', today()->subDays(6));

    $this->actingAs($this->member)
        ->get(route('circles.tracker', [
            'circle' => $this->circle,
            'from' => $opened->toDateString(),
            'to' => $closed->toDateString(),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('days', 3)
            ->where('members.data.1.wins.movement', 1)
            ->where('members.data.1.wins.learning', 1)
            ->where('members.data.1.wins.meditation', 0)
        );
});

test('a win late on the closing day is still inside the range', function () {
    shareWin($this->circle, $this->member, 'learning', today()->setTime(23, 45));

    $this->actingAs($this->member)
        ->get(route('circles.tracker', [
            'circle' => $this->circle,
            'from' => today()->toDateString(),
            'to' => today()->toDateString(),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('days', 1)
            ->where('members.data.1.wins.learning', 1)
        );
});

test('a win shared somewhere else is not counted here', function () {
    shareWin(Circle::factory()->create(), $this->member, 'meditation', today());

    Post::factory()
        ->has(WinMeditation::factory(), 'winMeditation')
        ->create(['user_id' => $this->member->id]);

    $this->actingAs($this->member)
        ->get(route('circles.tracker', $this->circle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('members.data.1.wins.meditation', 0));
});

test('the tracker carries each members streak', function () {
    // Not mass assignable — the streak is the streak action's to set. The last
    // win comes with it, since a run is only still running while that is
    // recent: without it these columns describe a streak that ended.
    $this->member->forceFill([
        'streak_days' => 12,
        'longest_streak' => 30,
        'last_win_on' => today(),
    ])->save();

    $this->actingAs($this->member)
        ->get(route('circles.tracker', $this->circle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('members.data.1.streak_days', 12)
            ->where('members.data.1.longest_streak', 30)
        );
});

test('a range that ends before it starts is refused', function () {
    $this->actingAs($this->member)
        ->get(route('circles.tracker', [
            'circle' => $this->circle,
            'from' => today()->toDateString(),
            'to' => today()->subWeek()->toDateString(),
        ]))
        ->assertSessionHasErrors('to');
});

test('the search narrows the tracker to the members named', function () {
    shareWin($this->circle, $this->member, 'meditation', today());

    $this->actingAs($this->member)
        ->get(route('circles.tracker', ['circle' => $this->circle, 'search' => 'bea']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('members.data', 1)
            ->where('members.data.0.full_name', 'Bea Member')
            // Narrowing the list leaves the counting alone: the same range in
            // the same circles, with fewer people listed against it.
            ->where('members.data.0.wins.meditation', 1)
            ->where('search', 'bea')
        );
});

test('the search matches a username as well as a name', function () {
    $this->owner->update(['username' => 'sitting_ada']);

    $this->actingAs($this->member)
        ->get(route('circles.tracker', ['circle' => $this->circle, 'search' => 'sitting']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('members.data', 1)
            ->where('members.data.0.full_name', 'Ada Owner')
        );
});

test('a search matching nobody empties the list rather than ignoring itself', function () {
    $this->actingAs($this->member)
        ->get(route('circles.tracker', ['circle' => $this->circle, 'search' => 'nobody here']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('members.data', 0)
            ->where('search', 'nobody here')
        );
});

test('a blank search lists everybody rather than looking for nothing', function () {
    $this->actingAs($this->member)
        ->get(route('circles.tracker', ['circle' => $this->circle, 'search' => '   ']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('members.data', 2)
            ->where('search', null)
        );
});

test('the search travels with the range and the circles counted', function () {
    $inner = Circle::factory()->create([
        'owner_id' => $this->owner->id,
        'parent_id' => $this->circle->id,
        'name' => 'Beginners',
    ]);
    CircleMembership::create([
        'user_id' => $this->member->id,
        'circle_id' => $inner->id,
        'joined_at' => now(),
    ]);

    shareWin($inner, $this->member, 'movement', today());

    $this->actingAs($this->member)
        ->get(route('circles.tracker', [
            'circle' => $this->circle,
            'circles' => [$inner->id],
            'from' => today()->subDays(6)->toDateString(),
            'to' => today()->toDateString(),
            'search' => 'bea',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('days', 7)
            ->where('selectedCircles', [$inner->id])
            ->where('search', 'bea')
            ->has('members.data', 1)
            ->where('members.data.0.wins.movement', 1)
        );
});

test('the tracker runs the same queries however many members there are', function () {
    $measure = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->member)
            ->get(route('circles.tracker', $this->circle))
            ->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    // Measured once and discarded, for the reason given in CircleIndexTest:
    // the reader's photo lands on the instance this test holds during the
    // first render, and the baseline has to be taken on the same footing.
    $measure();

    $few = $measure();

    foreach (User::factory()->count(6)->create() as $extra) {
        CircleMembership::create([
            'user_id' => $extra->id,
            'circle_id' => $this->circle->id,
            'joined_at' => now(),
        ]);
        shareWin($this->circle, $extra, 'movement', today());
    }

    expect($measure())->toBe($few);
});

test('only the owner can open the manage page', function () {
    $this->actingAs($this->member)
        ->get(route('circles.manage', $this->circle))
        ->assertForbidden();

    $this->actingAs($this->owner)
        ->get(route('circles.manage', $this->circle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('circles/manage'));
});

test('the owner can rename the circle', function () {
    $this->actingAs($this->owner)
        ->patch(route('circles.manage.update', $this->circle), [
            'name' => 'Evening Sitters',
            'description' => 'We sit after work.',
            'tag' => 'meditation',
            'icon_initial' => 'ES',
            'color_hex' => '#4F46E5',
        ])
        ->assertRedirect();

    expect($this->circle->refresh()->name)->toBe('Evening Sitters');
});

test('the owner can turn the circle private from the manage page', function () {
    // "1"/"0" is what the radio posts, and the `boolean` rule takes both.
    $this->actingAs($this->owner)
        ->patch(route('circles.manage.update', $this->circle), [
            'name' => 'Morning Sitters',
            'icon_initial' => 'MS',
            'color_hex' => '#4F46E5',
            'is_private' => '1',
        ])
        ->assertSessionHasNoErrors();

    expect($this->circle->refresh()->is_private)->toBeTrue();

    $this->actingAs($this->owner)
        ->patch(route('circles.manage.update', $this->circle), [
            'name' => 'Morning Sitters',
            'icon_initial' => 'MS',
            'color_hex' => '#4F46E5',
            'is_private' => '0',
        ])
        ->assertSessionHasNoErrors();

    expect($this->circle->refresh()->is_private)->toBeFalse();
});

test('the manage page is told whether the circle is private', function () {
    $this->circle->update(['is_private' => true]);

    // Without this the form has nothing to set the radio from, and a private
    // circle opens its own settings claiming to be public.
    $this->actingAs($this->owner)
        ->get(route('circles.manage', $this->circle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('circle.is_private', true));
});

test('a member cannot rename the circle', function () {
    $this->actingAs($this->member)
        ->patch(route('circles.manage.update', $this->circle), [
            'name' => 'Hijacked',
            'icon_initial' => 'H',
            'color_hex' => '#000000',
        ])
        ->assertForbidden();

    expect($this->circle->refresh()->name)->toBe('Morning Sitters');
});

test('a circle cannot take a name another circle already has', function () {
    Circle::factory()->create(['name' => 'Runners']);

    $this->actingAs($this->owner)
        ->patch(route('circles.manage.update', $this->circle), [
            'name' => 'Runners',
            'icon_initial' => 'MS',
            'color_hex' => '#4F46E5',
        ])
        ->assertSessionHasErrors('name');
});

test('saving without changing the name is not a clash with itself', function () {
    $this->actingAs($this->owner)
        ->patch(route('circles.manage.update', $this->circle), [
            'name' => 'Morning Sitters',
            'icon_initial' => 'MS',
            'color_hex' => '#4F46E5',
        ])
        ->assertSessionHasNoErrors();
});

test('the owner can remove a member', function () {
    $this->actingAs($this->owner)
        ->delete(route('circles.members.remove', [$this->circle, $this->member]))
        ->assertRedirect();

    expect($this->circle->members()->count())->toBe(1);
    expect($this->circle->refresh()->members_count)->toBe(1);
});

test('the owner cannot be removed from their own circle', function () {
    $this->actingAs($this->owner)
        ->delete(route('circles.members.remove', [$this->circle, $this->owner]))
        ->assertSessionHasErrors('user');

    expect($this->circle->members()->count())->toBe(2);
});

test('a member cannot remove anybody', function () {
    $this->actingAs($this->member)
        ->delete(route('circles.members.remove', [$this->circle, $this->owner]))
        ->assertForbidden();

    expect($this->circle->members()->count())->toBe(2);
});

test('the owner can delete the circle', function () {
    $this->actingAs($this->owner)
        ->delete(route('circles.destroy', $this->circle))
        ->assertRedirect(route('circles.index'));

    expect(Circle::find($this->circle->id))->toBeNull();
});

test('the circles page lists the circles the reader belongs to', function () {
    postInCircle($this->circle, $this->member);
    postInCircle($this->circle, $this->member);

    $this->actingAs($this->owner)
        ->get(route('circles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('circles/index')
            ->has('circles', 1)
            ->where('circles.0.name', 'Morning Sitters')
            ->where('circles.0.members_count', 2)
            ->where('circles.0.posts_count', 2)
            ->where('circles.0.can_manage', true)
        );
});

test('the circles page offers manage only on circles the reader owns', function () {
    $this->actingAs($this->member)
        ->get(route('circles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('circles.0.can_manage', false));
});

test('the circles page leaves out circles the reader has not joined', function () {
    Circle::factory()->create(['name' => 'Somebody Elses']);

    $this->actingAs($this->member)
        ->get(route('circles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('circles', 1));
});

test('somebody in no circles gets an empty list rather than an error', function () {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('circles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('circles', 0));
});

test('guests cannot see the circles page', function () {
    $this->get(route('circles.index'))->assertRedirect(route('login'));
});

test('the dashboard no longer carries a circles prop', function () {
    $this->actingAs($this->owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->missing('circles'));
});

/*
 * The manage page carries what its sub-circle card needs.
 *
 * The names have to match what the page destructures, and nothing shouts when
 * they do not: a mis-cased prop arrives as `undefined`, which is falsy, so the
 * card simply never renders and the page looks like the feature was never
 * built. That is exactly what happened — `can_add_sub_circle` for a page
 * reading `canAddSubCircle`.
 */
test('the manage page offers the owner a way to open a circle inside', function () {
    $this->actingAs($this->owner)
        ->get(route('circles.manage', $this->circle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('circles/manage')
            ->has('subCircles', 0)
            ->where('canAddSubCircle', true)
        );
});

test('a circle already inside another is not offered more', function () {
    $inner = Circle::factory()->create([
        'owner_id' => $this->owner->id,
        'parent_id' => $this->circle->id,
        'name' => 'Inner Circle',
    ]);

    $this->actingAs($this->owner)
        ->get(route('circles.manage', $inner))
        ->assertOk()
        // They do not nest, so the card is not drawn at all.
        ->assertInertia(fn ($page) => $page->where('canAddSubCircle', false));
});

test('the manage page lists the circles already inside', function () {
    Circle::factory()->create([
        'owner_id' => $this->member->id,
        'parent_id' => $this->circle->id,
        'name' => 'Beginners',
    ]);

    $this->actingAs($this->owner)
        ->get(route('circles.manage', $this->circle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('subCircles', 1)
            ->where('subCircles.0.name', 'Beginners')
            ->where('subCircles.0.owner.full_name', 'Bea Member')
        );
});

/*
 * The tracker counts the circles inside this one as well.
 *
 * A circle's tracker is the group's picture of itself, and one that stopped at
 * the outer wall would leave out most of what the group has been doing — the
 * smaller circles are where a lot of it happens. The picker narrows it back
 * down for anybody who wants one of them on its own.
 */
test('the tracker counts wins from the circles inside this one', function () {
    $inner = Circle::factory()->create([
        'owner_id' => $this->owner->id,
        'parent_id' => $this->circle->id,
        'name' => 'Beginners',
    ]);
    CircleMembership::create([
        'user_id' => $this->member->id,
        'circle_id' => $inner->id,
        'joined_at' => now(),
    ]);

    shareWin($this->circle, $this->member, 'meditation', today());
    shareWin($inner, $this->member, 'movement', today()->subDay());

    $this->actingAs($this->member)
        ->get(route('circles.tracker', $this->circle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('members.data.1.wins.meditation', 1)
            // Shared into the inner circle, and counted here all the same.
            ->where('members.data.1.wins.movement', 1)
            ->where('members.data.1.total', 2)
            ->has('circleOptions', 2)
            ->where('circleOptions.0.is_parent', true)
        );
});

test('the picker narrows the tracker to the circles named', function () {
    $inner = Circle::factory()->create([
        'owner_id' => $this->owner->id,
        'parent_id' => $this->circle->id,
        'name' => 'Beginners',
    ]);
    CircleMembership::create([
        'user_id' => $this->member->id,
        'circle_id' => $inner->id,
        'joined_at' => now(),
    ]);

    shareWin($this->circle, $this->member, 'meditation', today());
    shareWin($inner, $this->member, 'movement', today()->subDay());

    $this->actingAs($this->member)
        ->get(route('circles.tracker', ['circle' => $this->circle, 'circles' => [$inner->id]]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('members.data.0.wins.movement', 1)
            ->where('members.data.0.wins.meditation', 0)
            ->where('selectedCircles', [$inner->id])
        );
});

test('somebody in both circles is one row, not two', function () {
    $inner = Circle::factory()->create([
        'owner_id' => $this->owner->id,
        'parent_id' => $this->circle->id,
        'name' => 'Beginners',
    ]);
    CircleMembership::create([
        'user_id' => $this->member->id,
        'circle_id' => $inner->id,
        'joined_at' => now(),
    ]);

    // Owner and member, each once, however many of these circles they are in.
    $this->actingAs($this->member)
        ->get(route('circles.tracker', $this->circle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('members.data', 2));
});

test('a circle id from somewhere else cannot be counted here', function () {
    $theirs = Circle::factory()->create(['name' => 'Not Ours']);
    shareWin($theirs, $this->member, 'meditation', today());

    $this->actingAs($this->member)
        ->get(route('circles.tracker', ['circle' => $this->circle, 'circles' => [$theirs->id]]))
        ->assertOk()
        // Narrowed to nothing recognisable, so it falls back to this circle
        // and its own — never to a circle the reader simply named.
        ->assertInertia(fn ($page) => $page
            ->where('selectedCircles', [$this->circle->id])
            ->where('members.data.1.wins.meditation', 0)
        );
});

/*
 * Staff reach every circle through the policy, so the tools on the manage page
 * are already theirs — including the ones added for sub-circles. Asserted
 * rather than assumed: `CirclePolicy::before` is what makes it true, and a
 * change there would take these with it silently.
 */
test('staff can open a circle inside one they do not own', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->post(route('circles.sub.store', $this->circle), ['name' => 'Staff Made'])
        ->assertRedirect();

    expect($this->circle->subCircles()->where('name', 'Staff Made')->exists())->toBeTrue();
});

test('a circle opened inside another can be private on its own', function () {
    // Its own answer, not the parent's: a private room inside a public house is
    // the whole reason for the smaller room.
    $this->actingAs($this->owner)
        ->post(route('circles.sub.store', $this->circle), [
            'name' => 'Inner Quiet',
            'is_private' => '1',
        ])
        ->assertRedirect();

    expect($this->circle->subCircles()->where('name', 'Inner Quiet')->sole()->is_private)
        ->toBeTrue()
        ->and($this->circle->refresh()->is_private)->toBeFalse();
});

test('staff see the sub-circle tools on somebody else circle', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get(route('circles.manage', $this->circle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canAddSubCircle', true)
            // The staff-only hand-over, which is how an owner is assigned.
            ->where('circle.can_transfer_ownership', true)
        );
});

test('staff can invite somebody they do not follow', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $stranger = User::factory()->create(['full_name' => 'Zed Stranger']);

    $names = collect(
        $this->actingAs($admin)
            ->get(route('circles.manage', $this->circle))
            ->assertOk()
            ->viewData('page')['props']['candidates']
    )->pluck('full_name');

    // An owner only sees mutual follows here; staff are not on this screen as
    // a member, and a circle nobody has asked them into is the point of it.
    expect($names)->toContain('Zed Stranger');

    $this->actingAs($admin)
        ->post(route('circles.invitations.store', $this->circle), ['user_id' => $stranger->id])
        ->assertRedirect();
});

test('the posts tab keeps a public circle wall from a non-member', function () {
    postInCircle($this->circle, $this->member, ['caption' => 'Sat for twenty.']);

    /*
     * Being let through the door is not being let to read everything behind
     * it. A public circle admits anybody signed in, but a win on this wall was
     * addressed to its *members* — `all_circles` is a different answer from
     * `public`, and this page used to hand over the lot to whoever opened the
     * URL, having joined nothing.
     */
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('circles.posts', $this->circle))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('circles/posts')->has('posts.data', 0));
});

test('the posts tab shuts a non-member out of a private circle entirely', function () {
    postInCircle($this->circle, $this->member, ['caption' => 'Sat for twenty.']);
    $this->circle->update(['is_private' => true]);

    $this->actingAs(User::factory()->create())
        ->get(route('circles.posts', $this->circle))
        ->assertForbidden();
});
