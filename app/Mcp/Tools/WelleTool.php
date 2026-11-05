<?php

namespace App\Mcp\Tools;

use App\Concerns\ScopesToOwnedCircles;
use App\Models\Circle;
use App\Models\CircleMembership;
use App\Models\User;
use App\Support\Day;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tool;

/**
 * Base for every welle MCP tool.
 *
 * Owner side only: every tool answers about the circles the connected person
 * runs and nothing else. A member's circles, or anybody else's, never reach
 * the AI — asking for one is refused rather than quietly answered with
 * nothing, so the model knows it asked the wrong question.
 */
abstract class WelleTool extends Tool
{
    use ScopesToOwnedCircles;

    /**
     * The person the token belongs to.
     *
     * @throws AuthenticationException
     */
    protected function user(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthenticationException('No user on this MCP call.');
        }

        return $user;
    }

    /**
     * The optional `circle_id` input shared by tools that read a circle.
     *
     * @return array<string, mixed>
     */
    protected function circleSchema(JsonSchema $schema): array
    {
        return [
            'circle_id' => $schema->string()
                ->description('Only this circle. Omit for every circle you run. Ids come from list_my_circles.'),
        ];
    }

    /**
     * The circles asked about: the one named, or every circle the person runs.
     *
     * @return Collection<int, Circle>
     */
    protected function requestedCircles(Request $request): Collection
    {
        $validated = $request->validate([
            'circle_id' => ['nullable', 'string'],
        ]);

        $circles = $this->ownedCircles($this->user())
            ->when(isset($validated['circle_id']), fn ($query) => $query->whereKey($validated['circle_id']))
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        if (isset($validated['circle_id']) && $circles->isEmpty()) {
            throw ValidationException::withMessages([
                'circle_id' => 'You do not run that circle. Use list_my_circles to see the circles you run.',
            ]);
        }

        return $circles;
    }

    /**
     * The ids of these circles, for the queries below.
     *
     * @param  Collection<int, Circle>  $circles
     * @return list<string>
     */
    protected function idsOf(Collection $circles): array
    {
        return array_values($circles->map(fn (Circle $circle): string => $circle->id)->all());
    }

    /**
     * A `YYYY-MM-DD` input read as a whole local day, or the fallback.
     */
    protected function localDate(?string $value, Carbon $fallback): Carbon
    {
        return $value === null
            ? $fallback->copy()->startOfDay()
            : Carbon::createFromFormat('Y-m-d', $value, Day::zone())->startOfDay();
    }

    /**
     * The memberships of these circles, with their people, that existed by the
     * given moment — somebody who joined today cannot have missed yesterday.
     *
     * The caller is left out: an owner asking who is quiet means everybody
     * else.
     *
     * @param  list<string>  $circleIds
     * @return Collection<int, CircleMembership>
     */
    protected function membershipsBy(array $circleIds, Carbon $by): Collection
    {
        return CircleMembership::query()
            ->whereIn('circle_id', $circleIds)
            ->where('user_id', '!=', $this->user()->getKey())
            ->where('joined_at', '<=', Day::utc($by))
            ->whereHas('user')
            ->with('user:id,full_name,username,streak_days,last_win_on')
            ->get();
    }

    /**
     * When each person posted into each circle within the window, local.
     *
     * @param  list<string>  $circleIds
     * @return array<string, array<string, list<Carbon>>> circle id → user id → moments
     */
    protected function postMoments(array $circleIds, Carbon $from, Carbon $to): array
    {
        $rows = DB::table('circle_post')
            ->join('posts', 'posts.id', '=', 'circle_post.post_id')
            ->whereIn('circle_post.circle_id', $circleIds)
            ->whereBetween('posts.created_at', [Day::utc($from), Day::utc($to)])
            ->get(['circle_post.circle_id', 'posts.user_id', 'posts.created_at']);

        $moments = [];

        foreach ($rows as $row) {
            $moments[$row->circle_id][$row->user_id][] = Day::on(Carbon::parse($row->created_at, 'UTC'));
        }

        return $moments;
    }

    /**
     * The last time each person posted into each circle, ever, local.
     *
     * @param  list<string>  $circleIds
     * @return array<string, array<string, string>> circle id → user id → ISO moment
     */
    protected function lastPostedAt(array $circleIds): array
    {
        $rows = DB::table('circle_post')
            ->join('posts', 'posts.id', '=', 'circle_post.post_id')
            ->whereIn('circle_post.circle_id', $circleIds)
            ->groupBy('circle_post.circle_id', 'posts.user_id')
            ->selectRaw('circle_post.circle_id, posts.user_id, max(posts.created_at) as last_at')
            ->get();

        $last = [];

        foreach ($rows as $row) {
            $last[$row->circle_id][$row->user_id] = Day::on(Carbon::parse($row->last_at, 'UTC'))->toIso8601String();
        }

        return $last;
    }

    /**
     * One person, as the AI is told about them.
     *
     * @return array{user_id: string, full_name: string, username: string|null, role: string, joined_at: string, streak_days: int}
     */
    protected function describeMember(CircleMembership $membership): array
    {
        return [
            'user_id' => $membership->user_id,
            'full_name' => $membership->user->full_name,
            'username' => $membership->user->username,
            'role' => $membership->role,
            'joined_at' => Day::on($membership->joined_at)->toDateString(),
            'streak_days' => $membership->user->currentStreak(),
        ];
    }
}
