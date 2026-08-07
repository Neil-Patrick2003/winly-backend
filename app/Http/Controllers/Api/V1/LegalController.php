<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ResolveLegalDocument;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class LegalController extends Controller
{
    /**
     * Both legal documents, as structure the app draws natively.
     *
     * Unauthenticated, because the screen that reads it is reached from sign-up
     * — nobody has an account yet at the moment they are deciding whether to
     * agree to the terms.
     *
     * The same structure the web pages render, from the same config, so the
     * documents cannot say one thing in the app and another at the URL handed
     * to App Store Connect.
     */
    public function index(ResolveLegalDocument $resolve): JsonResponse
    {
        return response()->json(['data' => $resolve->all()]);
    }
}
