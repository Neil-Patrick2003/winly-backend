<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;

class PasswordResetLinkController extends Controller
{
    /**
     * Mint a password reset link for somebody, to be passed on by hand.
     *
     * The same link the "forgot password" email carries, made without sending
     * anything — for the person whose email never arrives, or who no longer
     * reaches the address they signed up with.
     *
     * Handed back through flash rather than as a prop, so it is never written
     * into the browser's history state and does not come back when somebody
     * steps backwards through the admin screens. It is a credential with a
     * short life, and it belongs on screen once.
     *
     * Each call replaces whatever token that account had, so the last link
     * made is the only one that works.
     */
    public function __invoke(User $user): RedirectResponse
    {
        $token = Password::createToken($user);

        Inertia::flash('resetLink', [
            'user_id' => $user->id,
            'full_name' => $user->full_name,
            // The reset screen reads the address out of the query string and
            // shows it back, so the link has to carry both halves.
            'url' => route('password.reset', [
                'token' => $token,
                'email' => $user->email,
            ]),
        ]);

        return back();
    }
}
