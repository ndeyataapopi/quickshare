<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    /**
     * Ensure the authenticated user has an active status.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->isActive()) {
            $status = $request->user()->status;

            if ($request->is('api/*') || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => "Your account is {$status}. Please contact support.",
                ], 403);
            }

            // For pending users, redirect to verification page
            if ($status === 'pending') {
                return redirect()->route('verification.notice');
            }

            abort(403, "Your account is {$status}. Please contact support.");
        }

        return $next($request);
    }
}
