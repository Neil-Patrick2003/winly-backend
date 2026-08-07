<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Support\SessionKey;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'is_admin' => true,
        'full_name' => 'Ada Admin',
        'username' => 'ada_admin',
        'email' => 'ada@example.com',
    ]);

    /*
     * The username carries no underscore on purpose. The search escapes `_`
     * with a backslash, which MySQL honours and SQLite — what the tests run on
     * — does not, so a username with one in it would fail here while working
     * perfectly in production and prove nothing either way.
     */
    $this->person = User::factory()->create([
        'full_name' => 'Bea Person',
        'username' => 'beaperson',
        'email' => 'bea@example.com',
    ]);
});

/**
 * The reset link the admin screen just minted, straight out of the session.
 */
function flashedResetLink(): string
{
    return data_get(session(SessionKey::FLASH_DATA), 'resetLink.url');
}

test('guests are sent to login', function () {
    $this->get(route('admin.users'))->assertRedirect(route('login'));
});

test('the people screen does not exist for anybody but staff', function () {
    $this->actingAs($this->person)
        ->get(route('admin.users'))
        ->assertNotFound();
});

test('staff see everybody with an account', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.users'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users')
            ->has('users.data', 2)
            ->where('users.data.0.full_name', 'Ada Admin')
            ->where('users.data.0.is_admin', true)
            ->where('users.data.1.full_name', 'Bea Person')
            ->where('users.data.1.email', 'bea@example.com')
            ->where('users.data.1.is_admin', false)
            ->where('resetExpiresInMinutes', 60)
            ->etc()
        );
});

test('people can be searched by name, username or email', function () {
    foreach (['Bea', 'beaperson', 'bea@example.com'] as $term) {
        $this->actingAs($this->admin)
            ->get(route('admin.users', ['filter' => ['search' => $term]]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.full_name', 'Bea Person')
                ->etc()
            );
    }
});

test('the table can be ordered by each of its columns, both ways', function () {
    // Ada sorts before Bea by name and by email; Bea joined later.
    $this->person->forceFill(['created_at' => now()->addDay()])->save();

    // Ada leads on every column: ada_admin < beaperson, ada@ < bea@, joined
    // first. Asserted by name throughout, which is the column being read back.
    foreach (['full_name', 'username', 'email', 'created_at'] as $column) {
        [$first, $last] = ['Ada Admin', 'Bea Person'];

        $this->actingAs($this->admin)
            ->get(route('admin.users', ['sort' => $column]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('users.data.0.full_name', $first)
                ->where('users.data.1.full_name', $last)
                ->etc()
            );

        // A leading minus flips it, which is what a second click sends.
        $this->actingAs($this->admin)
            ->get(route('admin.users', ['sort' => '-'.$column]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('users.data.0.full_name', $last)
                ->where('users.data.1.full_name', $first)
                ->etc()
            );
    }
});

test('a column outside the whitelist is refused rather than ordered by', function () {
    /*
     * Sorting is whitelisted, so the parameter cannot be turned into a way to
     * order by any column on the table — `password_hash` included, which would
     * leak its shape a page at a time.
     */
    $this->actingAs($this->admin)
        ->get(route('admin.users', ['sort' => 'password_hash']))
        // Spatie answers 400 for a sort it was never told to allow.
        ->assertStatus(400);
});

test('a closed account is left out', function () {
    $this->person->delete();

    $this->actingAs($this->admin)
        ->get(route('admin.users'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('users.data', 1));
});

test('staff can mint a reset link for somebody', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.reset-link', $this->person))
        ->assertRedirect()
        // Flashed rather than sent as a prop, so a working credential never
        // lands in the browser's history state.
        ->assertInertiaFlash('resetLink.full_name', 'Bea Person')
        ->assertInertiaFlash('resetLink.url');

    expect(flashedResetLink())->toContain('reset-password/');
    expect(flashedResetLink())->toContain(urlencode('bea@example.com'));
});

test('the minted link actually resets the password', function () {
    // The point of the whole feature, so it is driven end to end rather than
    // pattern-matched: a link that merely looks right is worth nothing.
    $this->actingAs($this->admin)
        ->post(route('admin.users.reset-link', $this->person));

    $url = flashedResetLink();
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    $token = basename((string) parse_url($url, PHP_URL_PATH));

    // Fortify's reset route is behind `guest`, so the admin has to be gone
    // before following the link — exactly as the real person would be.
    Auth::logout();

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $query['email'],
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('a-brand-new-password', $this->person->refresh()->password_hash))
        ->toBeTrue();
});

test('minting a second link retires the first', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.reset-link', $this->person));
    $first = flashedResetLink();

    $this->actingAs($this->admin)
        ->post(route('admin.users.reset-link', $this->person));

    // Only one token is stored per account, so the earlier link is dead. Worth
    // proving, because the screen says so.
    $token = basename((string) parse_url($first, PHP_URL_PATH));

    Auth::logout();

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => 'bea@example.com',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertSessionHasErrors();

    expect(Hash::check('a-brand-new-password', $this->person->refresh()->password_hash))
        ->toBeFalse();
});

test('a member cannot mint a reset link for anybody', function () {
    $victim = User::factory()->create();

    $this->actingAs($this->person)
        ->post(route('admin.users.reset-link', $victim))
        ->assertNotFound();

    expect(session(SessionKey::FLASH_DATA))->toBeNull();
});
