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

        if ($request->user()?->email_verified_at === null) {
            auth()->logout();

            abort(403, 'This account is pending verification. Contact support.');
        }

        return $next($request);
    }
}
