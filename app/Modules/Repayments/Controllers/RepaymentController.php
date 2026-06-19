<?php

namespace App\Modules\Repayments\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Loans\Models\Loan;
use App\Modules\Repayments\Services\RepaymentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RepaymentController extends Controller
{
    use ApiResponse;

    public function __construct(protected RepaymentService $repaymentService)
    {
    }

    // ─── Borrower: View Repayment Schedule ───────────────────────────

    public function schedule(Request $request, Loan $loan): JsonResponse
    {
        // Authorization: borrower can only view their own loans
        if ($loan->borrower_id !== $request->user()->id) {
            return $this->forbidden('You can only view your own loan schedules.');
        }

        $repayments = $this->repaymentService->getLoanRepayments($loan->id);

        return $this->success([
            'loan' => [
                'id' => $loan->id,
                'reference' => $loan->reference,
                'total_repayment' => $loan->total_repayment,
                'repayment_date' => $loan->repayment_date?->toDateString(),
            ],
            'repayments' => $repayments,
        ], 'Repayment schedule retrieved.');
    }

    // ─── Borrower: View Own Repayments ───────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['sometimes', 'string', 'in:pending,partial,paid,overdue,defaulted'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $repayments = $this->repaymentService->getBorrowerRepayments(
            $request->user()->id,
            $request->only(['status', 'per_page']),
        );

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

    // ─── Borrower: Make Repayment ────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'loan_id' => ['required', 'integer', 'exists:loans,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_method' => ['sometimes', 'string', 'in:bank_transfer,credit_card,debit_card'],
        ]);

        $loan = Loan::findOrFail($validated['loan_id']);

        // Authorization check
        if ($loan->borrower_id !== $request->user()->id) {
            return $this->forbidden('You can only repay your own loans.');
        }

        if (! $loan->isActive()) {
            return $this->error('Loan is not active. Status: ' . $loan->status, 422);
        }

        try {
            $repayment = $this->repaymentService->recordRepayment(
                $loan,
                $request->user(),
                (float) $validated['amount'],
                $validated['payment_method'] ?? 'bank_transfer',
            );

            return $this->success([
                'repayment' => $repayment,
                'loan_status' => $loan->fresh()->status,
            ], 'Repayment recorded successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    // ─── Borrower: View Single Repayment ─────────────────────────────

    public function show(Request $request, int $repayment): JsonResponse
    {
        $repayment = $this->repaymentService->getRepayment($repayment);

        if (! $repayment) {
            return $this->notFound('Repayment not found.');
        }

        // Authorization check
        if ($repayment->borrower_id !== $request->user()->id) {
            return $this->forbidden('You can only view your own repayments.');
        }

        return $this->success(['repayment' => $repayment], 'Repayment retrieved.');
    }

    // ─── Lender: View Earnings ────────────────────────────────────────

    public function lenderEarnings(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only lenders can view earnings
        if (! $user->hasRole('lender') && ! $user->hasRole('admin')) {
            return $this->forbidden('Only lenders can view earnings.');
        }

        $summary = $this->repaymentService->getLenderEarningsSummary($user->id);
        $repayments = $this->repaymentService->getLenderRepayments($user->id);

        return $this->success([
            'summary' => $summary,
            'repayments' => $repayments->items(),
            'meta' => [
                'current_page' => $repayments->currentPage(),
                'last_page' => $repayments->lastPage(),
                'per_page' => $repayments->perPage(),
                'total' => $repayments->total(),
            ],
        ], 'Lender earnings retrieved.');
    }

    // ─── Lender: View Earnings Summary ───────────────────────────────

    public function lenderSummary(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('lender') && ! $user->hasRole('admin')) {
            return $this->forbidden('Only lenders can view earnings.');
        }

        $summary = $this->repaymentService->getLenderEarningsSummary($user->id);

        return $this->success($summary, 'Lender earnings summary retrieved.');
    }
}
