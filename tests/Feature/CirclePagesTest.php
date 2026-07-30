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
    // Not mass assignable — the streak is the streak action's to set.
    $this->member->forceFill(['streak_days' => 12, 'longest_streak' => 30])->save();

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
