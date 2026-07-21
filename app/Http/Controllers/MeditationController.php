<?php

namespace App\Http\Controllers;

use App\Http\Requests\Meditation\IndexMeditationRequest;
use App\Http\Requests\Meditation\StoreMeditationRequest;
use App\Http\Requests\Meditation\UpdateMeditationRequest;
use App\Models\Meditation;
use App\Models\MeditationCategory;
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

        $meditations = Meditation::query()
            ->select(['id', 'category_id', 'title', 'description', 'thumbnail', 'audio_url', 'video_url', 'duration_minutes', 'created_at'])
            ->with('category:id,name,icon')
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
            'totalCount' => Inertia::once(fn (): int => Meditation::query()->count()),
            'totalMinutes' => Inertia::once(fn (): int => (int) Meditation::query()->sum('duration_minutes')),
            'perPageOptions' => IndexMeditationRequest::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * Show the form for creating a new meditation.
     */
    public function create(): Response
    {
        return Inertia::render('meditations/create', [
            'categories' => $this->categoryOptions(),
            'maxDuration' => Meditation::MAX_DURATION_MINUTES,
        ]);
    }

    /**
     * Store a newly created meditation.
     */
    public function store(StoreMeditationRequest $request): RedirectResponse
    {
        $meditation = Meditation::create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':title has been created.', ['title' => $meditation->title]),
        ]);

        return to_route('meditations.index');
    }

    /**
     * Show the form for editing the given meditation.
     */
    public function edit(Meditation $meditation): Response
    {
        return Inertia::render('meditations/edit', [
            'meditation' => $meditation->only([
                'id', 'category_id', 'title', 'description',
                'thumbnail', 'audio_url', 'video_url', 'duration_minutes',
            ]),
            'categories' => $this->categoryOptions(),
            'maxDuration' => Meditation::MAX_DURATION_MINUTES,
        ]);
    }

    /**
     * Update the given meditation.
     */
    public function update(UpdateMeditationRequest $request, Meditation $meditation): RedirectResponse
    {
        $meditation->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':title has been updated.', ['title' => $meditation->title]),
        ]);

        return to_route('meditations.index');
    }

    /**
     * Delete the given meditation.
     */
    public function destroy(Meditation $meditation): RedirectResponse
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
            ->select(['id', 'name', 'icon'])
            ->orderBy('name')
            ->get();
    }
}
