<?php

namespace App\Mcp\Tools;

use App\Models\Circle;
use App\Models\CircleMembership;
use App\Support\Day;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('members_without_posts')]
#[Description('Finds the members of your circles who did NOT post into the circle on a given day (defaults to yesterday). Use it for questions like "who didn\'t post yesterday in my circle?". Members who joined after that day are not counted, and you are left out.')]
#[IsReadOnly]
class MembersWithoutPostsTool extends WelleTool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.Day::now()->toDateString()],
        ], [
            'date.before_or_equal' => 'The date cannot be in the future.',
        ]);

        $from = $this->localDate($validated['date'] ?? null, Day::now()->subDay());
        $to = $from->copy()->endOfDay();

        $circles = $this->requestedCircles($request);
        $circleIds = $this->idsOf($circles);

        $memberships = $this->membershipsBy($circleIds, $to)->groupBy('circle_id');
        $posted = $this->postMoments($circleIds, $from, $to);
        $lastPosted = $this->lastPostedAt($circleIds);

        return Response::json([
            'date' => $from->toDateString(),
            'timezone' => Day::zone()->getName(),
            'circles' => $circles->map(function (Circle $circle) use ($memberships, $posted, $lastPosted): array {
                $members = $memberships->get($circle->id, collect());

                $missing = $members
                    ->reject(fn (CircleMembership $membership): bool => isset($posted[$circle->id][$membership->user_id]))
                    ->sortBy(fn (CircleMembership $membership): string => $membership->user->full_name)
                    ->map(fn (CircleMembership $membership): array => [
                        ...$this->describeMember($membership),
                        'last_posted_at' => $lastPosted[$circle->id][$membership->user_id] ?? null,
                    ])
                    ->values();

                return [
                    'circle_id' => $circle->id,
                    'circle_name' => $circle->name,
                    'members_counted' => $members->count(),
                    'posted_count' => $members->count() - $missing->count(),
                    'did_not_post_count' => $missing->count(),
                    'did_not_post' => $missing->all(),
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
            'date' => $schema->string()->format('date')
                ->description('The day to check, YYYY-MM-DD, in the app\'s timezone. Defaults to yesterday.'),
            ...$this->circleSchema($schema),
        ];
    }
}
