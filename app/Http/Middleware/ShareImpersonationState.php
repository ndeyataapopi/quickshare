<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ShareImpersonationState
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->has('impersonate.original_id') && auth()->check()) {
            View::share('impersonating', true);
            View::share('impersonatedUser', auth()->user());
            View::share('impersonateAdminUrl', route('admin.impersonate.stop'));
        } else {
            View::share('impersonating', false);
            View::share('impersonatedUser', null);
            View::share('impersonateAdminUrl', null);
        }

        return $next($request);
    }
}
