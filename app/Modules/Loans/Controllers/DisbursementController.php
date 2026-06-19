<?php

namespace App\Modules\Loans\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Loans\Models\DisbursementTransaction;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Services\DisbursementService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisbursementController extends Controller
{
    use ApiResponse;

    public function __construct(protected DisbursementService $disbursementService)
    {
    }

    // ─── View Loan Disbursements ───────────────────────────────────

    public function forLoan(Request $request, Loan $loan): JsonResponse
    {
        $user = $request->user();

        // Borrowers can only view their own loan disbursements
        if (! $user->hasRole('admin') && $loan->borrower_id !== $user->id) {
            return $this->notFound('Loan not found.');
        }

        $disbursements = $this->disbursementService->getLoanDisbursements($loan->id);

        return $this->success([
            'disbursements' => $disbursements,
            'loan' => [
                'id' => $loan->id,
                'reference' => $loan->reference,
                'status' => $loan->status,
                'funded_amount' => $loan->funded_amount,
                'platform_fee' => $loan->platform_fee,
            ],
        ], 'Disbursements retrieved.');
    }

    // ─── View Single Disbursement ────────────────────────────────────

    public function show(Request $request, int $disbursement): JsonResponse
    {
        $transaction = $this->disbursementService->getDisbursement($disbursement);

        if (! $transaction) {
            return $this->notFound('Disbursement transaction not found.');
        }

        // Check authorization
        $loan = $transaction->loan;
        $user = $request->user();

        // Admin can view all, borrower can view own loan, others need permission
        if (! $user->hasRole('admin') && $loan->borrower_id !== $user->id) {
            return $this->forbidden('You do not have permission to view this disbursement.');
        }

        return $this->success(['disbursement' => $transaction], 'Disbursement retrieved.');
    }

    // ─── Admin: Manual Retry ─────────────────────────────────────────

    public function retry(Request $request, int $disbursement): JsonResponse
    {
        $transaction = DisbursementTransaction::find($disbursement);

        if (! $transaction) {
            return $this->notFound('Disbursement transaction not found.');
        }

        if (! $transaction->canRetry()) {
            return $this->error(
                'Disbursement cannot be retried. Status: ' . $transaction->status,
                422,
            );
        }

        $newTransaction = $this->disbursementService->retryDisbursement($transaction);

        // Dispatch job to process the retry
        \App\Modules\Loans\Jobs\ProcessDisbursementJob::dispatch($newTransaction->id);

        return $this->success([
            'original_disbursement' => $transaction,
            'new_disbursement' => $newTransaction,
        ], 'Disbursement retry initiated.');
    }

    // ─── Admin: Reconcile Disbursement ────────────────────────────────

    public function reconcile(Request $request, int $disbursement): JsonResponse
    {
        $request->validate([
            'reconciliation_data' => ['sometimes', 'array'],
        ]);

        $transaction = DisbursementTransaction::find($disbursement);

        if (! $transaction) {
            return $this->notFound('Disbursement transaction not found.');
        }

        try {
            $reconciled = $this->disbursementService->reconcile(
                $transaction,
                $request->user()->email,
                $request->input('reconciliation_data'),
            );

            return $this->success([
                'disbursement' => $reconciled,
            ], 'Disbursement reconciled successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    // ─── Admin: Reconciliation Report ────────────────────────────────

    public function reconciliationReport(Request $request): JsonResponse
    {
        $report = $this->disbursementService->getReconciliationReport();

        return $this->success($report, 'Reconciliation report retrieved.');
    }

    // ─── Admin: Pending Disbursements ────────────────────────────────

    public function pending(Request $request): JsonResponse
    {
        $pending = $this->disbursementService->getPendingDisbursements();

        return $this->success([
            'count' => $pending->count(),
            'disbursements' => $pending,
        ], 'Pending disbursements retrieved.');
    }

    // ─── Admin: Failed Needing Retry ─────────────────────────────────

    public function failedRetry(Request $request): JsonResponse
    {
        $failed = $this->disbursementService->getFailedDisbursementsNeedingRetry();

        return $this->success([
            'count' => $failed->count(),
            'disbursements' => $failed,
        ], 'Failed disbursements needing retry retrieved.');
    }
}
