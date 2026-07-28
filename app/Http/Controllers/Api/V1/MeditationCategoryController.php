<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CachedMeditationCategories;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MeditationCategoryController extends Controller
{
    /**
     * List every meditation category, newest edits included.
     */
    public function index(CachedMeditationCategories $categories): JsonResponse
    {
        return response()->json(['data' => $categories()]);
    }
}
