<?php

namespace App\Http\Controllers;

use App\Http\Requests\Meditation\IndexMeditationRequest;
use App\Http\Requests\Meditation\StoreMeditationRequest;
use App\Http\Requests\Meditation\UpdateMeditationRequest;
use App\Models\MeditationCategory;
use App\Models\MeditationItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class MeditationController extends Controller
{
    /**
     * Display a filtered, sorted, paginated list of meditations.
     */
    public function index(IndexMeditationRequest $request): Response
    {
        $filters = $request->filters();

        $meditations = MeditationItem::query()
            ->select(['id', 'category_id', 'title', 'instructions', 'thumbnail', 'audio_url', 'video_url', 'duration_minutes', 'created_at'])
            ->with('category:id,label,icon')
            ->search($filters['search'])
            ->inCategory($filters['category_id'])
            ->durationBetween($filters['min_duration'], $filters['max_duration'])
            ->createdBetween($filters['from'], $filters['to'])
            ->sorted($filters['sort'], $filters['direction'])
            ->paginate($filters['per_page'])
            ->withQueryString();

        return Inertia::render('meditations/index', [
            'meditations' => $meditations,
            'filters' => $filters,
            'categories' => Inertia::once(fn (): Collection => $this->categoryOptions()),
            'totalCount' => Inertia::once(fn (): int => MeditationItem::query()->count()),
            'totalMinutes' => Inertia::once(fn (): int => (int) MeditationItem::query()->sum('duration_minutes')),
            'maxDuration' => MeditationItem::MAX_DURATION_MINUTES,
            'perPageOptions' => IndexMeditationRequest::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * Store a newly created meditation.
     */
    public function store(StoreMeditationRequest $request): RedirectResponse
    {
        $meditation = MeditationItem::create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':title has been created.', ['title' => $meditation->title]),
        ]);

        return back();
    }

    /**
     * Update the given meditation.
     */
    public function update(UpdateMeditationRequest $request, MeditationItem $meditation): RedirectResponse
    {
        $meditation->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':title has been updated.', ['title' => $meditation->title]),
        ]);

        return back();
    }

    /**
     * Delete the given meditation.
     */
    public function destroy(MeditationItem $meditation): RedirectResponse
    {
        $meditation->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':title has been deleted.', ['title' => $meditation->title]),
        ]);

        return back();
    }

    /**
     * The categories offered by the form and the category filter.
     *
     * @return Collection<int, MeditationCategory>
     */
    protected function categoryOptions(): Collection
    {
        return MeditationCategory::query()
            ->select(['id', 'label', 'icon'])
            ->orderBy('label')
            ->get();
    }
}
