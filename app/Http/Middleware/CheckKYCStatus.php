<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckKYCStatus
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        
        // Skip KYC check for non-client users
        if (!$user || !$user->hasRole('client')) {
            return $next($request);
        }
        
        // Allow access to KYC upload page
        if ($request->routeIs('client.kyc.*')) {
            return $next($request);
        }
        
        // Allow access to logout
        if ($request->routeIs('logout')) {
            return $next($request);
        }
        
        // Allow access to profile for basic info
        if ($request->routeIs('client.profile.*')) {
            return $next($request);
        }
        
        // Allow access if user is admin-approved (active status without KYC)
        if ($user->status === 'active') {
            return $next($request);
        }
        
        // Check if user has approved KYC
        $kyc = $user->kycSubmission;
        
        if (!$kyc || $kyc->status !== 'approved') {
            // Redirect to KYC upload page with message
            return redirect()->route('client.kyc.upload')
                ->with('warning', 'You must complete KYC verification to access this feature.');
        }
        
        return $next($request);
    }
}
