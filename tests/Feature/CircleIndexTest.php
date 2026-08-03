<?php

use App\Models\Circle;
use App\Models\CircleMembership;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->join = function (Circle $circle): Circle {
        CircleMembership::create([
            'user_id' => $this->user->id,
            'circle_id' => $circle->id,
            'joined_at' => now(),
        ]);

        return $circle;
    };

    $this->busy = ($this->join)(Circle::factory()->create([
        'name' => 'Morning Movers',
        'tag' => 'fitness',
        'description' => 'Early risers building a daily step habit.',
    ]));

    $this->quiet = ($this->join)(Circle::factory()->create([
        'name' => 'Page Turners',
        'tag' => 'reading',
    ]));

    postInCircle($this->busy, $this->user, ['created_at' => now()->subDay()]);
    postInCircle($this->quiet, $this->user, ['created_at' => now()->subMonths(2)]);
});

test('a card carries everything it draws', function () {
    $this->actingAs($this->user)
        ->get(route('circles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('circles/index')
            ->has('circles', 2)
            ->where('circles.0.name', 'Morning Movers')
            ->where('circles.0.tag', 'fitness')
            ->where('circles.0.posts_count', 1)
            ->where('circles.0.is_active', true)
            ->has('circles.0.faces', 1)
            ->has('circles.0.wash')
        );
});

test('a circle nobody has posted in lately reads as quiet', function () {
    $this->actingAs($this->user)
        ->get(route('circles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('circles.1.is_active', false));
});

test('the wash is decided by the name, and does not wander', function () {
    $first = $this->actingAs($this->user)->get(route('circles.index'));
    $again = $this->actingAs($this->user)->get(route('circles.index'));

    expect($first->viewData('page')['props']['circles'][0]['wash'])
        ->toBe($again->viewData('page')['props']['circles'][0]['wash'])
        ->toBeIn(['blue', 'lavender', 'pink', 'peach', 'mint', 'butter']);
});

test('the tabs narrow the list and count the whole of it', function () {
    $this->actingAs($this->user)
        ->get(route('circles.index', ['filter' => ['state' => 'active']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('circles', 1)
            ->where('circles.0.name', 'Morning Movers')
            // The counts describe everything, not the tab that is open.
            ->where('counts.all', 2)
            ->where('counts.active', 1)
            ->where('counts.quiet', 1)
        );

    $this->actingAs($this->user)
        ->get(route('circles.index', ['filter' => ['state' => 'quiet']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('circles', 1)
            ->where('circles.0.name', 'Page Turners')
        );
});

test('search looks at the name, the tag and the description', function () {
    foreach (['Movers', 'fitness', 'step habit'] as $term) {
        $this->actingAs($this->user)
            ->get(route('circles.index', ['filter' => ['search' => $term]]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('circles', 1)
                ->where('circles.0.name', 'Morning Movers')
            );
    }

    $this->actingAs($this->user)
        ->get(route('circles.index', ['filter' => ['search' => 'nothing like this']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('circles', 0));
});

test('an unknown filter is refused rather than guessed at', function () {
    $this->actingAs($this->user)
        ->get(route('circles.index', ['filter' => ['state' => 'sideways']]))
        ->assertSessionHasErrors('filter.state');
});

test('the list runs the same queries however many circles there are', function () {
    $measure = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->user)->get(route('circles.index'))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    /*
     * Measured once and thrown away first. `actingAs` hands the guard a model
     * this test already holds, so the reader's own photo is loaded onto it by
     * the first render and is already there for the second — an asymmetry of
     * the test rather than of the page, which resolves its reader from the
     * database and carries the photo with it. The baseline is taken after,
     * so what is compared is two renders on the same footing.
     */
    $measure();

    $few = $measure();

    foreach (range(1, 5) as $index) {
        $circle = ($this->join)(Circle::factory()->create(['name' => "Circle {$index}"]));
        postInCircle($circle, $this->user);
    }

    expect($measure())->toBe($few);
});

test('a member can start a circle and lands inside it', function () {
    $this->actingAs($this->user)
        ->post(route('circles.store'), [
            'name' => 'Evening Sitters',
            'description' => 'We sit after work.',
            'tag' => 'meditation',
        ])
        ->assertRedirect();

    $circle = Circle::where('name', 'Evening Sitters')->sole();

    expect($circle->owner_id)->toBe($this->user->id)
        ->and($circle->icon_initial)->toBe('E')
        ->and($circle->color_hex)->toStartWith('#')
        ->and($circle->members_count)->toBe(1);

    // Making one puts you in it.
    expect(CircleMembership::where('circle_id', $circle->id)
        ->where('user_id', $this->user->id)
        ->exists())->toBeTrue();
});

test('two circles cannot share a name', function () {
    $this->actingAs($this->user)
        ->post(route('circles.store'), ['name' => 'Morning Movers'])
        ->assertSessionHasErrors('name');
});

test('guests cannot start a circle', function () {
    $this->post(route('circles.store'), ['name' => 'Uninvited'])
        ->assertRedirect(route('login'));
});
