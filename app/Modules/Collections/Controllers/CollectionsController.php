<?php

namespace App\Modules\Collections\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Collections\Jobs\ProcessDailyCollectionsJob;
use App\Modules\Collections\Models\CollectionLog;
use App\Modules\Collections\Services\CollectionService;
use App\Modules\Loans\Models\Loan;
use App\Modules\Repayments\Models\Repayment;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollectionsController extends Controller
{
    use ApiResponse;

    public function __construct(protected CollectionService $collectionService)
    {
    }

    // ─── Admin: Collections Dashboard ────────────────────────────────

    public function dashboard(Request $request): JsonResponse
    {
        $dashboard = $this->collectionService->getCollectionsDashboard();

        return $this->success($dashboard, 'Collections dashboard retrieved.');
    }

    // ─── Admin: View Collection History for Loan ─────────────────────

    public function loanHistory(Request $request, Loan $loan): JsonResponse
    {
        $history = $this->collectionService->getCollectionHistory($loan->id);

        return $this->success([
            'loan' => [
                'id' => $loan->id,
                'reference' => $loan->reference,
                'status' => $loan->status,
            ],
            'history' => $history,
            'stats' => $this->collectionService->getBorrowerCollectionStats($loan->borrower_id),
        ], 'Collection history retrieved.');
    }

    // ─── Admin: View Borrower Collection Stats ───────────────────────

    public function borrowerStats(Request $request, int $borrower): JsonResponse
    {
        $stats = $this->collectionService->getBorrowerCollectionStats($borrower);

        return $this->success($stats, 'Borrower collection stats retrieved.');
    }

    // ─── Admin: Trigger Daily Reminders ──────────────────────────────

    public function triggerReminders(Request $request): JsonResponse
    {
        ProcessDailyCollectionsJob::dispatch();

        return $this->success([], 'Daily collections processing triggered.');
    }

    // ─── Admin: Manual Reminder ───────────────────────────────────────

    public function sendManualReminder(Request $request, Repayment $repayment): JsonResponse
    {
        $validated = $request->validate([
            'channel' => ['required', 'string', 'in:sms,email,whatsapp,voice'],
            'message' => ['sometimes', 'string', 'max:1000'],
        ]);

        try {
            $log = $this->collectionService->sendReminder(
                $repayment->loan,
                $repayment,
                $validated['channel'],
                'manual',
                $validated['message'] ?? null,
            );

            return $this->success([
                'log' => $log,
            ], 'Manual reminder sent.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Manual reminder failed', [
                'error' => $e->getMessage(),
                'repayment_id' => $repayment->id,
                'loan_id' => $repayment->loan_id,
            ]);
            return $this->error($e->getMessage(), 422);
        }
    }

    // ─── Admin: Process Escalations ──────────────────────────────────

    public function processEscalations(Request $request): JsonResponse
    {
        $stats = $this->collectionService->processEscalations();

        return $this->success($stats, 'Escalations processed.');
    }

    // ─── Admin: Process Default Workflow ─────────────────────────────

    public function processDefault(Request $request, Loan $loan): JsonResponse
    {
        if ($loan->status !== 'defaulted') {
            return $this->error('Loan must be marked as defaulted first.', 422);
        }

        try {
            $this->collectionService->processDefaultWorkflow($loan);

            return $this->success([], 'Default workflow processed.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    // ─── Admin: View Collection Logs ─────────────────────────────────

    public function logs(Request $request): JsonResponse
    {
        $request->validate([
            'action_type' => ['sometimes', 'string'],
            'channel' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string', 'in:pending,sent,delivered,failed'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = CollectionLog::with(['loan', 'borrower'])
            ->orderBy('created_at', 'desc');

        if ($request->has('action_type')) {
            $query->byAction($request->input('action_type'));
        }

        if ($request->has('channel')) {
            $query->byChannel($request->input('channel'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $logs = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'message' => 'Collection logs retrieved.',
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
            'links' => [
                'first' => $logs->url(1),
                'last' => $logs->url($logs->lastPage()),
                'prev' => $logs->previousPageUrl(),
                'next' => $logs->nextPageUrl(),
            ],
        ]);
    }

    // ─── Admin: Update Log Status (Webhook Handler) ────────────────────

    public function updateLogStatus(Request $request, int $log): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:delivered,failed,opened'],
            'timestamp' => ['sometimes', 'date'],
        ]);

        $log = CollectionLog::find($log);

        if (! $log) {
            return $this->notFound('Collection log not found.');
        }

        match ($validated['status']) {
            'delivered' => $log->markAsDelivered(),
            'opened' => $log->markAsOpened(),
            'failed' => $log->markAsFailed($request->input('reason', 'Delivery failed')),
            default => null,
        };

        return $this->success(['log' => $log->fresh()], 'Log status updated.');
    }
}
