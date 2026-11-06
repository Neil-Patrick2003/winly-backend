<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

/**
 * The Privacy Policy, the Terms of Service and how to delete an account, as
 * plain public web pages.
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
     * How to delete an account, including without the app.
     *
     * Google Play asks for this as its own link in the listing, reachable by
     * someone who has already uninstalled the app.
     */
    public function deleteAccount(): View
    {
        return view('legal.delete-account', $this->details());
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
