<?php

namespace App\Modules\Repayments\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Modules\Loans\Events\LoanDefaulted;
use App\Modules\Repayments\Jobs\CheckOverdueRepaymentsJob;
use App\Modules\Repayments\Models\Repayment;
use App\Modules\Repayments\Services\RepaymentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RepaymentAdminController extends Controller
{
    use ApiResponse;

    public function __construct(protected RepaymentService $repaymentService)
    {
    }

    // ─── Admin: View All Repayments ──────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['sometimes', 'string', 'in:pending,partial,paid,overdue,defaulted'],
            'loan_id' => ['sometimes', 'integer', 'exists:loans,id'],
            'borrower_id' => ['sometimes', 'integer', 'exists:users,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Repayment::with(['loan', 'borrower'])
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('loan_id')) {
            $query->where('loan_id', $request->input('loan_id'));
        }

        if ($request->has('borrower_id')) {
            $query->where('borrower_id', $request->input('borrower_id'));
        }

        $repayments = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Repayments retrieved.',
            'data' => $repayments->items(),
            'meta' => [
                'current_page' => $repayments->currentPage(),
                'last_page' => $repayments->lastPage(),
                'per_page' => $repayments->perPage(),
                'total' => $repayments->total(),
            ],
            'links' => [
                'first' => $repayments->url(1),
                'last' => $repayments->url($repayments->lastPage()),
                'prev' => $repayments->previousPageUrl(),
                'next' => $repayments->nextPageUrl(),
            ],
        ]);
    }

    // ─── Admin: View Single Repayment ──────────────────────────────────

    public function show(Request $request, int $repayment): JsonResponse
    {
        $repayment = $this->repaymentService->getRepayment($repayment);

        if (! $repayment) {
            return $this->notFound('Repayment not found.');
        }

        return $this->success(['repayment' => $repayment], 'Repayment retrieved.');
    }

    // ─── Admin: View Overdue Summary ─────────────────────────────────

    public function overdueSummary(Request $request): JsonResponse
    {
        $summary = $this->repaymentService->getOverdueSummary();

        return $this->success($summary, 'Overdue summary retrieved.');
    }

    // ─── Admin: View Upcoming Repayments ─────────────────────────────

    public function upcoming(Request $request): JsonResponse
    {
        $request->validate([
            'days' => ['sometimes', 'integer', 'min:1', 'max:30'],
        ]);

        $days = $request->input('days', 7);
        $repayments = $this->repaymentService->getUpcomingRepayments($days);

        return $this->success([
            'days_ahead' => $days,
            'count' => $repayments->count(),
            'repayments' => $repayments,
        ], 'Upcoming repayments retrieved.');
    }

    // ─── Admin: Trigger Overdue Check ──────────────────────────────────

    public function triggerOverdueCheck(Request $request): JsonResponse
    {
        CheckOverdueRepaymentsJob::dispatch();

        return $this->success([], 'Overdue check triggered.');
    }

    // ─── Admin: Mark as Defaulted ────────────────────────────────────

    public function markDefaulted(Request $request, int $repayment): JsonResponse
    {
        $repayment = Repayment::find($repayment);

        if (! $repayment) {
            return $this->notFound('Repayment not found.');
        }

        if (! in_array($repayment->status, ['overdue', 'partial'])) {
            return $this->error('Only overdue or partial repayments can be marked as defaulted.', 422);
        }

        $repayment->update(['status' => 'defaulted']);

        // Update loan status
        $loan = $repayment->loan;
        $loan->update(['status' => 'defaulted']);

        LoanDefaulted::dispatch($loan->fresh(), $repayment->id);

        return $this->success([
            'repayment' => $repayment->fresh(),
            'loan_status' => $loan->fresh()->status,
        ], 'Repayment and loan marked as defaulted.');
    }

    // ─── Admin: Waive Penalty ────────────────────────────────────────

    public function waivePenalty(Request $request, int $repayment): JsonResponse
    {
        $repayment = Repayment::find($repayment);

        if (! $repayment) {
            return $this->notFound('Repayment not found.');
        }

        if ($repayment->penalty <= 0) {
            return $this->error('No penalty to waive.', 422);
        }

        $waivedAmount = $repayment->penalty;

        $repayment->update([
            'penalty' => 0,
            'notes' => ($repayment->notes ? $repayment->notes . "\n" : '') .
                'Penalty of ' . $waivedAmount . ' waived by admin on ' . now()->toDateString(),
        ]);

        ActivityLog::create([
            'user_id' => $repayment->borrower_id,
            'actor_id' => auth()->id(),
            'action' => 'repayment.penalty_waived',
            'description' => "Penalty of R{$waivedAmount} waived for repayment #{$repayment->id} on loan #{$repayment->loan_id}",
            'subject_type' => Repayment::class,
            'subject_id' => $repayment->id,
            'loan_id' => $repayment->loan_id,
            'repayment_id' => $repayment->id,
            'amount' => (float) $waivedAmount,
            'metadata' => ['admin_id' => auth()->id()],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $this->success([
            'repayment' => $repayment->fresh(),
            'waived_amount' => $waivedAmount,
        ], 'Penalty waived successfully.');
    }
}
