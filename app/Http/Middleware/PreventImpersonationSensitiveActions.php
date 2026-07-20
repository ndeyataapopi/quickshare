<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventImpersonationSensitiveActions
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->has('impersonate.original_id')) {
            $blocked = config('impersonation.blocked_route_names', []);

            if (in_array($request->route()?->getName(), $blocked, true)) {
                abort(403, 'This action is not allowed while impersonating a user.');
            }
        }

        return $next($request);
    }
}
