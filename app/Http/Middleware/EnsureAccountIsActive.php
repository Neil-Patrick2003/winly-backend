<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns away a disabled account on every signed-in request.
 *
 * Disabling already revokes tokens and sessions, so this is the second lock:
 * it catches a session or token minted after the fact, such as by resetting
 * the password.
 */
class EnsureAccountIsActive
{
    public const MESSAGE = 'This account has been disabled. Contact support if you think this is a mistake.';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->disabled_at === null) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            $user->tokens()->delete();

            abort(403, self::MESSAGE);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', self::MESSAGE);
    }
}
