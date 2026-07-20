<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    public function start(Request $request, User $user): RedirectResponse
    {
        $admin = $request->user();

        if (! $admin || ! $admin->can('impersonate_users')) {
            abort(403, 'Unauthorized.');
        }

        if ($admin->is($user)) {
            return redirect()->route('admin.users.index')->with('error', 'You cannot impersonate yourself.');
        }

        if (! $user->hasRole('client')) {
            return redirect()->route('admin.users.index')->with('error', 'Only client accounts can be impersonated.');
        }

        if ($request->session()->has('impersonate.original_id')) {
            return redirect()->route('admin.users.index')->with('error', 'You are already impersonating a user.');
        }

        ActivityLog::create([
            'user_id' => $admin->id,
            'action' => 'impersonation.started',
            'description' => "Admin {$admin->first_name} {$admin->last_name} started impersonating client {$user->first_name} {$user->last_name}",
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'metadata' => [
                'admin_id' => $admin->id,
                'admin_name' => $admin->first_name.' '.$admin->last_name,
                'admin_email' => $admin->email,
                'client_id' => $user->id,
                'client_name' => $user->first_name.' '.$user->last_name,
                'client_email' => $user->email,
                'started_at' => now()->toIso8601String(),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $request->session()->put('impersonate.original_id', $admin->id);
        $request->session()->put('impersonate.client_id', $user->id);

        Auth::loginUsingId($user->id);

        return redirect()->route('client.dashboard');
    }

    public function stop(Request $request): RedirectResponse
    {
        $originalAdminId = $request->session()->get('impersonate.original_id');

        if (! $originalAdminId) {
            return redirect()->route('client.dashboard');
        }

        $client = Auth::user();

        ActivityLog::create([
            'user_id' => $originalAdminId,
            'action' => 'impersonation.ended',
            'description' => "Admin returned from impersonating client {$client?->first_name} {$client?->last_name}",
            'subject_type' => User::class,
            'subject_id' => $client?->id,
            'metadata' => [
                'admin_id' => $originalAdminId,
                'client_id' => $client?->id,
                'client_name' => $client ? $client->first_name.' '.$client->last_name : null,
                'ended_at' => now()->toIso8601String(),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $request->session()->forget(['impersonate.original_id', 'impersonate.client_id']);

        Auth::loginUsingId((int) $originalAdminId);

        return redirect()->route('admin.dashboard');
    }
}
