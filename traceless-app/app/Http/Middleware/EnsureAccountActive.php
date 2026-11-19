<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A suspended account keeps its data but loses access to the application,
 * including every authenticated client route.
 */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isSuspended()) {
            auth()->logout();

            abort(403, 'This account has been suspended. Contact support.');
        }

        return $next($request);
    }
}
