<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

/**
 * The Privacy Policy and the Terms of Service, as plain public web pages.
 *
 * Public on purpose: the app stores link to them from the listing, and the
 * sign-up screen links to them before anyone has an account. The app opens
 * these same addresses rather than keeping a second copy of the wording.
 */
class LegalController extends Controller
{
    public function privacy(): View
    {
        return view('legal.privacy', $this->details());
    }

    public function terms(): View
    {
        return view('legal.terms', $this->details());
    }

    /**
     * @return array<string, mixed>
     */
    protected function details(): array
    {
        return [
            'appName' => config('app.name'),
            'operator' => config('legal.operator'),
            'contactEmail' => config('legal.contact_email'),
            'country' => config('legal.country'),
            'minimumAge' => config('legal.minimum_age'),
            'updated' => Carbon::parse(config('legal.updated'))->format('F j, Y'),
        ];
    }
}
