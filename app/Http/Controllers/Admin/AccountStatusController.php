<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Moderation\DisableAccount;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Switching an account off and on from the People page.
 *
 * Off: signed out everywhere, cannot sign in, and hidden from everyone else.
 * Nothing is deleted, which is what makes switching it back on possible.
 */
class AccountStatusController extends Controller
{
    public function disable(Request $request, User $user, DisableAccount $accounts): RedirectResponse
    {
        if ($user->is_admin || $user->is($request->user())) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Staff accounts cannot be disabled here.']);

            return back();
        }

        $accounts->disable($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Disabled @'.$user->username.'.']);

        return back();
    }

    public function enable(User $user, DisableAccount $accounts): RedirectResponse
    {
        $accounts->enable($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Enabled @'.$user->username.' again.']);

        return back();
    }
}
