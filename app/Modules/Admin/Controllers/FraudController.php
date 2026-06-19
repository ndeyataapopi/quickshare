<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\FraudFlag;
use App\Modules\Admin\Services\FraudDetectionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FraudController extends Controller
{
    use ApiResponse;

    public function __construct(protected FraudDetectionService $fraudService)
    {
    }

    // ─── Get Fraud Summary ─────────────────────────────────────────────

    public function summary(Request $request): JsonResponse
    {
        $summary = $this->fraudService->getFraudSummary();

        return $this->success($summary, 'Fraud summary retrieved.');
    }

    // ─── Get Review Queue ────────────────────────────────────────────

    public function reviewQueue(Request $request): JsonResponse
    {
        $request->validate([
            'severity' => ['sometimes', 'string', 'in:low,medium,high,critical'],
            'flag_type' => ['sometimes', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $queue = $this->fraudService->getReviewQueue($request->only(['severity', 'flag_type', 'per_page']));

        return $this->success($queue, 'Review queue retrieved.');
    }

    // ─── Get Flag Details ────────────────────────────────────────────

    public function show(Request $request, int $flag): JsonResponse
    {
        $flag = $this->fraudService->getFlagDetails($flag);

        if (! $flag) {
            return $this->notFound('Fraud flag not found.');
        }

        return $this->success(['flag' => $flag], 'Flag details retrieved.');
    }

    // ─── Mark Flag Under Review ──────────────────────────────────────

    public function markUnderReview(Request $request, FraudFlag $flag): JsonResponse
    {
        $flag->markUnderReview($request->user()->id, $request->input('notes'));

        return $this->success(['flag' => $flag->fresh()], 'Flag marked as under review.');
    }

    // ─── Confirm Fraud ─────────────────────────────────────────────────

    public function confirm(Request $request, FraudFlag $flag): JsonResponse
    {
        $validated = $request->validate([
            'resolution_notes' => ['required', 'string', 'max:1000'],
            'actions' => ['sometimes', 'array'],
        ]);

        $flag->markConfirmed(
            $request->user()->id,
            $validated['resolution_notes'],
            $validated['actions'] ?? []
        );

        // Take actions on user if specified
        if (! empty($validated['actions'])) {
            $this->executeActions($flag->subject, $validated['actions']);
        }

        return $this->success(['flag' => $flag->fresh()], 'Fraud confirmed and actions taken.');
    }

    // ─── Mark False Positive ─────────────────────────────────────────

    public function markFalsePositive(Request $request, FraudFlag $flag): JsonResponse
    {
        $validated = $request->validate([
            'resolution_notes' => ['required', 'string', 'max:1000'],
        ]);

        $flag->markFalsePositive($request->user()->id, $validated['resolution_notes']);

        return $this->success(['flag' => $flag->fresh()], 'Flag marked as false positive.');
    }

    // ─── Resolve Flag ────────────────────────────────────────────────

    public function resolve(Request $request, FraudFlag $flag): JsonResponse
    {
        $validated = $request->validate([
            'resolution_notes' => ['required', 'string', 'max:1000'],
        ]);

        $flag->markResolved($request->user()->id, $validated['resolution_notes']);

        return $this->success(['flag' => $flag->fresh()], 'Flag resolved.');
    }

    // ─── Trigger Platform-wide Scan ──────────────────────────────────

    public function triggerScan(Request $request): JsonResponse
    {
        $stats = $this->fraudService->scanAllUsers($request->user()->id);

        return $this->success($stats, 'Platform-wide fraud scan completed.');
    }

    // ─── Get Flag Types ────────────────────────────────────────────────

    public function flagTypes(Request $request): JsonResponse
    {
        return $this->success(
            FraudDetectionService::FLAG_TYPES,
            'Flag types retrieved.'
        );
    }

    // ─── Helper: Execute Actions ─────────────────────────────────────

    protected function executeActions($subject, array $actions): void
    {
        foreach ($actions as $action) {
            match ($action) {
                'suspend_user' => $subject->update(['status' => 'suspended']),
                'block_borrowing' => $subject->update(['can_borrow' => false]),
                'notify_compliance' => $this->notifyCompliance($subject, $action),
                default => null,
            };
        }
    }

    protected function notifyCompliance($subject, string $action): void
    {
        // Send notification to compliance team
        \Illuminate\Support\Facades\Log::info('Compliance notification sent', [
            'subject_id' => $subject->id,
            'action' => $action,
        ]);
    }
}
