<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Moderation\ResolveReport;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\Story;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * The moderation queue: what members reported, and the three answers to it.
 *
 * Both app stores expect reports to be acted on within 24 hours, so this page
 * opens on what is still waiting, oldest first.
 */
class ReportController extends Controller
{
    protected const PER_PAGE = 20;

    /** The tabs across the top, and what each shows. */
    protected const STATUSES = ['pending', 'actioned', 'dismissed', 'all'];

    public function index(Request $request): Response
    {
        $status = $request->string('status')->value() ?: 'pending';

        if (! in_array($status, self::STATUSES, true)) {
            $status = 'pending';
        }

        $filter = fn ($query) => $query->when($status !== 'all', fn ($inner) => $inner->where('status', $status));

        /*
         * One card per reported thing, not per report: five people flagging
         * the same post is one decision, and the count says how loudly.
         *
         * Waiting ones oldest first, so nothing sits at the bottom past its 24
         * hours. Handled ones newest first, which is what you look back through.
         */
        $groups = Report::query()
            ->select('reportable_type', 'reportable_id')
            ->selectRaw('count(*) as total, min(created_at) as first_at, max(created_at) as last_at')
            ->tap($filter)
            ->groupBy('reportable_type', 'reportable_id')
            ->when(
                $status === 'pending',
                fn ($query) => $query->orderBy('first_at'),
                fn ($query) => $query->orderByDesc('last_at'),
            )
            ->orderBy('reportable_id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // Every report behind the cards on this page, newest first.
        $reports = Report::query()
            ->with('reporter')
            ->tap($filter)
            ->where(function ($query) use ($groups): void {
                foreach ($groups->items() as $group) {
                    $query->orWhere(fn ($pair) => $pair
                        ->where('reportable_type', $group->reportable_type)
                        ->where('reportable_id', $group->reportable_id));
                }
            })
            ->latest()
            ->get();

        // What each one is about, with what its preview needs, per type.
        $reports->loadMorph('reportable', [
            Post::class => ['user', 'winMeditation.media', 'winLearning.media', 'winMovement.media'],
            Comment::class => ['user'],
            Story::class => ['user'],
        ]);

        $byTarget = $reports->groupBy(fn (Report $report) => $report->reportable_type.'|'.$report->reportable_id);

        return Inertia::render('admin/reports', [
            'reports' => $groups->through(fn (Report $group): array => $this->card(
                collect($byTarget->get($group->reportable_type.'|'.$group->reportable_id, []))->values(),
            )),
            'status' => $status,
            // Things waiting, not reports waiting: the same count as the cards.
            'pendingCount' => DB::query()
                ->fromSub(
                    Report::query()
                        ->where('status', Report::STATUS_PENDING)
                        ->select('reportable_type', 'reportable_id')
                        ->groupBy('reportable_type', 'reportable_id'),
                    'waiting',
                )
                ->count(),
        ]);
    }

    public function remove(Request $request, Report $report, ResolveReport $resolve): RedirectResponse
    {
        return $this->act(fn () => $resolve->removeContent($report, $request->user()), 'Deleted, and its reports closed.');
    }

    public function disable(Request $request, Report $report, ResolveReport $resolve): RedirectResponse
    {
        return $this->act(function () use ($resolve, $report, $request): string {
            $author = $resolve->disableAuthor($report, $request->user());

            return 'Disabled @'.$author?->username.'. They are signed out and hidden.';
        });
    }

    public function dismiss(Request $request, Report $report, ResolveReport $resolve): RedirectResponse
    {
        return $this->act(fn () => $resolve->dismiss($report, $request->user()), 'Dismissed.');
    }

    /**
     * Run one decision and say how it went.
     *
     * @param  callable(): (string|void)  $decision
     */
    protected function act(callable $decision, ?string $message = null): RedirectResponse
    {
        try {
            $said = $decision();
        } catch (InvalidArgumentException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => is_string($said) ? $said : $message]);

        return back();
    }

    /**
     * One reported thing, with everybody who reported it.
     *
     * The newest report stands for the group: the buttons act on it, and
     * acting on one closes them all.
     *
     * @param  Collection<int, Report>  $reports  Newest first, never empty.
     * @return array<string, mixed>
     */
    protected function card(Collection $reports): array
    {
        $newest = $reports->firstOrFail();
        $target = $newest->reportable;
        $author = ResolveReport::authorOf($target);
        $collection = $reports;

        return [
            'id' => $newest->id,
            'type' => $newest->typeName(),
            'status' => $newest->status,
            'times_reported' => $reports->count(),
            // Most common first, so the card leads with what most people said.
            'reasons' => $collection
                ->countBy(fn (Report $report) => $report->reasonLabel())
                ->sortDesc()
                ->keys()
                ->values()
                ->all(),
            'notes' => $collection
                ->filter(fn (Report $report) => filled($report->note))
                ->map(fn (Report $report) => [
                    'by' => $report->reporter?->username ? '@'.$report->reporter->username : 'Someone',
                    'text' => $report->note,
                ])
                ->values()
                ->all(),
            'first_reported_at' => $collection->min('created_at')?->toIso8601String(),
            'reviewed_at' => $newest->reviewed_at?->toIso8601String(),
            'reporters' => $collection
                ->map(fn (Report $report) => $report->reporter)
                ->filter()
                ->map(fn (User $user) => $this->person($user))
                ->values()
                ->all(),
            'author' => $author ? $this->person($author) : null,
            'target' => $target ? $this->preview($target) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function person(User $user): array
    {
        return [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'username' => $user->username,
            'avatar_url' => $user->avatar_url,
            'is_admin' => $user->is_admin,
            'disabled' => $user->disabled_at !== null,
        ];
    }

    /**
     * Enough of the reported thing to judge it without leaving the page.
     *
     * @return array{text: string|null, images: array<int, string>, detail: string|null}
     */
    protected function preview(Model $target): array
    {
        return match (true) {
            $target instanceof Post => [
                'text' => $target->caption,
                'images' => collect([$target->winMeditation, $target->winLearning, $target->winMovement])
                    ->filter()
                    ->flatMap(fn ($win) => $win->media)
                    ->filter(fn ($media) => str_starts_with((string) $media->mime_type, 'image/'))
                    ->map(fn ($media) => url($media->getUrl()))
                    ->take(4)
                    ->values()
                    ->all(),
                'detail' => collect(['winMeditation' => 'Meditation', 'winLearning' => 'Learning', 'winMovement' => 'Movement'])
                    ->filter(fn (string $label, string $relation) => $target->{$relation} !== null)
                    ->implode(' · ') ?: null,
            ],
            $target instanceof Comment => ['text' => $target->text, 'images' => [], 'detail' => 'Comment'],
            $target instanceof Story => [
                'text' => $target->caption,
                'images' => array_filter([$target->image_url]),
                'detail' => 'Story',
            ],
            $target instanceof User => [
                'text' => $target->bio,
                'images' => array_filter([$target->avatar_url]),
                'detail' => 'Profile',
            ],
            default => ['text' => null, 'images' => [], 'detail' => null],
        };
    }
}
