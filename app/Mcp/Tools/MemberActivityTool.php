<?php

namespace App\Mcp\Tools;

use App\Models\Circle;
use App\Models\CircleMembership;
use App\Support\Day;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('member_activity')]
#[Description('How active each member of your circles was over a period: how many posts they shared into the circle, on how many different days, and when they last posted. Defaults to the last 7 days. Use it for "who is most / least active", streaks and check-ins.')]
#[IsReadOnly]
class MemberActivityTool extends WelleTool
{
    /** The longest period one call may cover. */
    private const MAX_DAYS = 92;

    private const DEFAULT_DAYS = 7;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $to = $this->localDate($validated['to'] ?? null, Day::now())->endOfDay();
        $from = $this->localDate($validated['from'] ?? null, $to->copy()->subDays(self::DEFAULT_DAYS - 1));

        if ($from->greaterThan($to)) {
            throw ValidationException::withMessages(['from' => '`from` must be on or before `to`.']);
        }

        if ($from->diffInDays($to) >= self::MAX_DAYS) {
            throw ValidationException::withMessages(['from' => 'The period can be at most '.self::MAX_DAYS.' days.']);
        }

        $circles = $this->requestedCircles($request);
        $circleIds = $this->idsOf($circles);

        $memberships = $this->membershipsBy($circleIds, $to)->groupBy('circle_id');
        $moments = $this->postMoments($circleIds, $from, $to);
        $lastPosted = $this->lastPostedAt($circleIds);

        return Response::json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'timezone' => Day::zone()->getName(),
            'circles' => $circles->map(function (Circle $circle) use ($memberships, $moments, $lastPosted): array {
                $members = $memberships->get($circle->id, collect())
                    ->map(function (CircleMembership $membership) use ($circle, $moments, $lastPosted): array {
                        /** @var list<Carbon> $posts */
                        $posts = $moments[$circle->id][$membership->user_id] ?? [];

                        return [
                            ...$this->describeMember($membership),
                            'posts_in_period' => count($posts),
                            'days_posted_in_period' => collect($posts)
                                ->map(fn (Carbon $at): string => $at->toDateString())
                                ->unique()
                                ->count(),
                            'last_posted_at' => $lastPosted[$circle->id][$membership->user_id] ?? null,
                        ];
                    })
                    ->sortBy([['days_posted_in_period', 'desc'], ['posts_in_period', 'desc'], ['full_name', 'asc']])
                    ->values();

                return [
                    'circle_id' => $circle->id,
                    'circle_name' => $circle->name,
                    'members' => $members->all(),
                ];
            })->all(),
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->format('date')
                ->description('First day, YYYY-MM-DD, inclusive. Defaults to 6 days before `to`.'),
            'to' => $schema->string()->format('date')
                ->description('Last day, YYYY-MM-DD, inclusive. Defaults to today.'),
            ...$this->circleSchema($schema),
        ];
    }
}
