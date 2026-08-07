<?php

namespace App\Http\Controllers;

use App\Actions\ResolveLegalDocument;
use Illuminate\View\View;

class LegalController extends Controller
{
    /**
     * Render one legal document as a public web page.
     *
     * These URLs are what App Store Connect is given and what any crawler sees,
     * so they stay outside the Inertia app and outside authentication: a
     * reviewer reading a policy should not need a session or a JavaScript
     * bundle to boot first.
     */
    public function show(string $document, ResolveLegalDocument $resolve): View
    {
        return view('legal.document', [
            'document' => $resolve->handle($document),
            'updatedAt' => config("legal.{$document}_updated_at"),
        ]);
    }
}
