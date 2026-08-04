<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Keep everybody but staff out.
     *
     * Answers 404 rather than 403, so the admin screens do not announce
     * themselves to a signed-in member who guesses the address. There is
     * nothing here for them either way, and a 403 would confirm the page is
     * real.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_admin === true, 404);

        return $next($request);
    }
}
