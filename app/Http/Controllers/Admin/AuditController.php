<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $event = $request->input('event');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $source = $request->input('source', '');

        $auditLogs = collect();
        $activityLogs = collect();

        if ($source === '' || $source === 'audit') {
            $auditQuery = AuditLog::with(['user'])->latest();

            if ($search) {
                $auditQuery->whereHas('user', fn($u) => $u->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))
                    ->orWhere('event', 'like', "%{$search}%")
                    ->orWhere('auditable_type', 'like', "%{$search}%");
            }

            if ($event) {
                $auditQuery->where('event', $event);
            }

            if ($dateFrom) {
                $auditQuery->whereDate('created_at', '>=', $dateFrom);
            }

            if ($dateTo) {
                $auditQuery->whereDate('created_at', '<=', $dateTo);
            }

            $auditLogs = $auditQuery->get()->map(function ($log) {
                return [
                    'id' => $log->id,
                    'source' => 'audit',
                    'user' => $log->user,
                    'action' => $log->event,
                    'auditable_type' => $log->auditable_type,
                    'auditable_id' => $log->auditable_id,
                    'old_values' => $log->old_values,
                    'new_values' => $log->new_values,
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                    'actor_id' => null,
                    'previous_status' => null,
                    'new_status' => null,
                    'description' => null,
                    'amount' => null,
                    'metadata' => null,
                    'created_at' => $log->created_at,
                ];
            });
        }

        if ($source === '' || $source === 'activity') {
            $activityQuery = ActivityLog::with(['user', 'actor'])->latest();

            if ($search) {
                $activityQuery->whereHas('user', fn($u) => $u->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }

            if ($event) {
                $activityQuery->where('action', 'like', "%{$event}%");
            }

            if ($dateFrom) {
                $activityQuery->whereDate('created_at', '>=', $dateFrom);
            }

            if ($dateTo) {
                $activityQuery->whereDate('created_at', '<=', $dateTo);
            }

            $activityLogs = $activityQuery->get()->map(function ($log) {
                return [
                    'id' => $log->id,
                    'source' => 'activity',
                    'user' => $log->user,
                    'action' => $log->action,
                    'auditable_type' => $log->subject_type,
                    'auditable_id' => $log->subject_id,
                    'old_values' => null,
                    'new_values' => null,
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                    'actor_id' => $log->actor_id,
                    'actor' => $log->actor,
                    'previous_status' => $log->previous_status,
                    'new_status' => $log->new_status,
                    'description' => $log->description,
                    'amount' => $log->amount,
                    'metadata' => $log->metadata,
                    'created_at' => $log->created_at,
                ];
            });
        }

        $merged = $auditLogs->merge($activityLogs)->sortByDesc('created_at')->values();

        $perPage = 50;
        $currentPage = $request->input('page', 1);
        $items = $merged->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $logs = new LengthAwarePaginator(
            $items,
            $merged->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $stats = [
            'total' => AuditLog::count() + ActivityLog::count(),
            'today' => AuditLog::whereDate('created_at', today())->count() + ActivityLog::whereDate('created_at', today())->count(),
            'this_week' => AuditLog::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count() + ActivityLog::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'this_month' => AuditLog::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count() + ActivityLog::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
        ];

        return view('admin.audit.index', compact('logs', 'stats'));
    }

    public function show(Request $request, string $source, int $id)
    {
        if ($source === 'audit') {
            $log = AuditLog::with(['user'])->findOrFail($id);
            $data = [
                'source' => 'audit',
                'id' => $log->id,
                'user' => $log->user,
                'action' => $log->event,
                'auditable_type' => $log->auditable_type,
                'auditable_id' => $log->auditable_id,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at,
            ];
        } else {
            $log = ActivityLog::with(['user', 'actor'])->findOrFail($id);
            $data = [
                'source' => 'activity',
                'id' => $log->id,
                'user' => $log->user,
                'actor' => $log->actor,
                'action' => $log->action,
                'description' => $log->description,
                'auditable_type' => $log->subject_type,
                'auditable_id' => $log->subject_id,
                'old_values' => null,
                'new_values' => null,
                'previous_status' => $log->previous_status,
                'new_status' => $log->new_status,
                'amount' => $log->amount,
                'metadata' => $log->metadata,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at,
            ];
        }

        return view('admin.audit.show', compact('data'));
    }
}
