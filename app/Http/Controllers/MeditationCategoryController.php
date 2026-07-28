<?php

namespace App\Http\Controllers;

use App\Http\Requests\MeditationCategory\IndexMeditationCategoryRequest;
use App\Http\Requests\MeditationCategory\StoreMeditationCategoryRequest;
use App\Http\Requests\MeditationCategory\UpdateMeditationCategoryRequest;
use App\Models\MeditationCategory;
use App\Models\MeditationItem;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MeditationCategoryController extends Controller
{
    /**
     * Display a filtered, sorted, paginated list of categories.
     */
    public function index(IndexMeditationCategoryRequest $request): Response
    {
        $filters = $request->filters();

        $categories = MeditationCategory::query()
            ->select(['id', 'label', 'slug', 'icon', 'description', 'created_at'])
            ->search($filters['search'])
            ->createdBetween($filters['from'], $filters['to'])
            ->sorted($filters['sort'], $filters['direction'])
            ->paginate($filters['per_page'])
            ->withQueryString();

        return Inertia::render('meditation-categories/index', [
            'categories' => $categories,
            'filters' => $filters,
            'totalCount' => Inertia::once(fn (): int => MeditationCategory::query()->count()),
            'meditationCount' => Inertia::once(fn (): int => MeditationItem::query()->count()),
            'iconOptions' => MeditationCategory::ICONS,
            'perPageOptions' => IndexMeditationCategoryRequest::PER_PAGE_OPTIONS,
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(StoreMeditationCategoryRequest $request): RedirectResponse
    {
        $category = MeditationCategory::create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name has been created.', ['name' => $category->label]),
        ]);

        return back();
    }

    /**
     * Update the given category.
     */
    public function update(UpdateMeditationCategoryRequest $request, MeditationCategory $meditationCategory): RedirectResponse
    {
        $meditationCategory->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name has been updated.', ['name' => $meditationCategory->label]),
        ]);

        return back();
    }

    /**
     * Delete the given category.
     */
    public function destroy(MeditationCategory $meditationCategory): RedirectResponse
    {
        $meditationCategory->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name has been deleted.', ['name' => $meditationCategory->label]),
        ]);

        return back();
    }
}
