<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Admin\Services\FraudDetectionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    use ApiResponse;

    public function __construct(protected FraudDetectionService $fraudService)
    {
    }

    // ─── List All Users ────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['sometimes', 'string', 'in:active,pending,suspended,inactive'],
            'role' => ['sometimes', 'string', 'in:borrower,lender,admin'],
            'search' => ['sometimes', 'string', 'min:2'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = User::with(['roles', 'trustScoreHistories' => fn ($q) => $q->latest()->limit(1)])
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('role')) {
            $query->role($request->input('role'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('national_id', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved.',
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
            'links' => [
                'first' => $users->url(1),
                'last' => $users->url($users->lastPage()),
                'prev' => $users->previousPageUrl(),
                'next' => $users->nextPageUrl(),
            ],
        ]);
    }

    // ─── View Single User ──────────────────────────────────────────────

    public function show(Request $request, User $user): JsonResponse
    {
        $user->load([
            'roles',
            'loans' => fn ($q) => $q->latest()->limit(10),
            'fundingTransactions' => fn ($q) => $q->latest()->limit(10),
            'trustScoreHistories' => fn ($q) => $q->latest()->limit(10),
            'collectionLogs' => fn ($q) => $q->latest()->limit(10),
        ]);

        return $this->success(['user' => $user], 'User retrieved.');
    }

    // ─── Update User Status ──────────────────────────────────────────

    public function updateStatus(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:active,pending,suspended,inactive'],
            'reason' => ['sometimes', 'string', 'max:500'],
        ]);

        $previousStatus = $user->status;

        $user->update(['status' => $validated['status']]);

        // Log the status change
        \App\Models\ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'user.status_updated',
            'description' => "User {$user->email} status changed from {$previousStatus} to {$validated['status']}",
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'metadata' => [
                'previous_status' => $previousStatus,
                'new_status' => $validated['status'],
                'reason' => $validated['reason'] ?? null,
            ],
        ]);

        return $this->success(['user' => $user->fresh()], 'User status updated.');
    }

    // ─── Update User Role ──────────────────────────────────────────────

    public function updateRole(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'in:borrower,lender,admin'],
        ]);

        // Remove existing roles and assign new one
        $user->syncRoles([$validated['role']]);

        return $this->success(['user' => $user->fresh()], 'User role updated.');
    }

    // ─── Adjust Trust Score ────────────────────────────────────────────

    public function adjustTrustScore(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'adjustment' => ['required', 'numeric'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $trustService = app(\App\Modules\TrustScore\Services\TrustScoreService::class);
        
        $updatedUser = $trustService->adjustScore(
            $user,
            $validated['adjustment'],
            $validated['reason'],
            'manual_adjustment',
            ['admin_id' => $request->user()->id],
        );

        return $this->success([
            'user' => $updatedUser,
            'new_trust_score' => $updatedUser->trust_score,
        ], 'Trust score adjusted.');
    }

    // ─── Scan User for Fraud ─────────────────────────────────────────

    public function scanFraud(Request $request, User $user): JsonResponse
    {
        $flags = $this->fraudService->scanUser($user);

        return $this->success([
            'user_id' => $user->id,
            'flags' => $flags,
            'risk_level' => $this->calculateRiskLevel($flags),
        ], 'Fraud scan completed.');
    }

    // ─── Get Fraud Summary ─────────────────────────────────────────────

    public function fraudSummary(Request $request): JsonResponse
    {
        $summary = $this->fraudService->getFraudSummary();

        return $this->success($summary, 'Fraud summary retrieved.');
    }

    // ─── Get User Activity Log ─────────────────────────────────────────

    public function activityLog(Request $request, User $user): JsonResponse
    {
        $activities = \App\Models\ActivityLog::where('subject_id', $user->id)
            ->where('subject_type', User::class)
            ->orWhere('user_id', $user->id)
            ->latest()
            ->paginate(50);

        return response()->json([
            'success' => true,
            'message' => 'Activity log retrieved.',
            'data' => $activities->items(),
            'meta' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
            ],
        ]);
    }

    // ─── Helper Methods ──────────────────────────────────────────────

    protected function calculateRiskLevel(array $flags): string
    {
        $highCount = collect($flags)->where('severity', 'high')->count();
        $mediumCount = collect($flags)->where('severity', 'medium')->count();

        if ($highCount > 0) {
            return 'high';
        }

        if ($mediumCount > 1) {
            return 'medium';
        }

        return 'low';
    }
}
