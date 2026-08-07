<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UserController extends Controller
{
    /**
     * How many people the table shows before paging.
     */
    protected const PER_PAGE = 25;

    /**
     * The columns the table may be ordered by.
     *
     * Whitelisted rather than taken from the request, or the sort parameter
     * would be a way to order by any column on the users table — `password_hash`
     * and `remember_token` included, which leaks their shape a page at a time.
     *
     * @var list<string>
     */
    protected const SORTABLE = ['full_name', 'username', 'email', 'created_at'];

    /**
     * Everybody with an account, as a table that can be ordered and searched.
     *
     * Searchable across name, username and email together, because the three
     * are what somebody asking for help will have given you and rarely the one
     * you happen to be looking under.
     *
     * Closed accounts stay out: the model soft deletes, and somebody who has
     * left is not a person to be minting reset links for.
     */
    public function index(Request $request): Response
    {
        $users = QueryBuilder::for(User::query())
            ->allowedFilters(
                AllowedFilter::callback('search', function (Builder $query, mixed $term): void {
                    if (! is_string($term) || blank($term)) {
                        return;
                    }

                    $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

                    $query->where(fn (Builder $inner) => $inner
                        ->where('full_name', 'like', $like)
                        ->orWhere('username', 'like', $like)
                        ->orWhere('email', 'like', $like));
                }),
            )
            ->defaultSort('full_name')
            ->allowedSorts(...self::SORTABLE)
            // A second key, so people sharing a name keep a stable order
            // between pages instead of swapping places as you leaf through.
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'username' => $user->username,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
                'is_admin' => $user->is_admin,
                'email_verified' => $user->email_verified_at !== null,
                'joined_at' => $user->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/users', [
            'users' => $users,
            'search' => $request->string('filter.search')->value(),
            'sort' => $request->string('sort')->value() ?: 'full_name',
            // Shown beside the link, so whoever passes it on knows how long
            // they have before it stops working.
            'resetExpiresInMinutes' => (int) config('auth.passwords.users.expire'),
        ]);
    }
}
