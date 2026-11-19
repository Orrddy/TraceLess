<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the /admin area. A client must never reach an admin route even if the UI
 * hides the link, so this is the only place access is decided for the browser.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            abort(403, 'This area is restricted to administrators.');
        }

        if ($user->isSuspended()) {
            abort(403, 'This administrator account is suspended.');
        }

        return $next($request);
    }
}
