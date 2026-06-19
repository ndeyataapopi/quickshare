<?php

namespace App\Modules\Funding\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Funding\Services\FundingService;
use App\Modules\Loans\Models\Loan;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FundingController extends Controller
{
    use ApiResponse;

    public function __construct(protected FundingService $fundingService)
    {
    }

    // ─── Lender: Browse Available Loans ──────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['sometimes', 'string', 'in:pending,confirmed,cancelled,refunded'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $portfolio = $this->fundingService->getLenderPortfolio(
            $request->user(),
            $request->only(['status', 'per_page']),
        );

        return response()->json([
            'success' => true,
            'message' => 'Portfolio retrieved.',
            'data' => $portfolio->items(),
            'meta' => [
                'current_page' => $portfolio->currentPage(),
                'last_page' => $portfolio->lastPage(),
                'per_page' => $portfolio->perPage(),
                'total' => $portfolio->total(),
            ],
            'links' => [
                'first' => $portfolio->url(1),
                'last' => $portfolio->url($portfolio->lastPage()),
                'prev' => $portfolio->previousPageUrl(),
                'next' => $portfolio->nextPageUrl(),
            ],
        ]);
    }

    // ─── Lender: View Portfolio Summary ─────────────────────────────

    public function portfolio(Request $request): JsonResponse
    {
        $summary = $this->fundingService->getLenderPortfolioSummary($request->user());

        return $this->success($summary, 'Portfolio summary retrieved.');
    }

    // ─── Lender: Fund a Loan ────────────────────────────────────────

    public function store(Request $request, Loan $loan): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:100'],
        ]);

        $transaction = $this->fundingService->fund(
            $request->user(),
            $loan,
            (float) $validated['amount'],
        );

        return $this->success([
            'transaction' => $transaction,
            'loan_status' => $loan->fresh()->status,
            'remaining_funding' => $this->fundingService->getRemainingFunding($loan->fresh()),
        ], 'Funding transaction initiated. Processing will complete shortly.');
    }

    // ─── Lender: View Funding Transaction ─────────────────────────

    public function show(Request $request, int $fundingTransaction): JsonResponse
    {
        $transaction = $request->user()
            ->fundingTransactions()
            ->with('loan')
            ->find($fundingTransaction);

        if (! $transaction) {
            return $this->notFound('Funding transaction not found.');
        }

        return $this->success(['transaction' => $transaction]);
    }

    // ─── Lender: Cancel Pending Funding ─────────────────────────────

    public function cancel(Request $request, int $fundingTransaction): JsonResponse
    {
        $transaction = $request->user()
            ->fundingTransactions()
            ->find($fundingTransaction);

        if (! $transaction) {
            return $this->notFound('Funding transaction not found.');
        }

        $cancelled = $this->fundingService->cancelFunding($transaction);

        return $this->success([
            'transaction' => $cancelled,
            'loan_status' => $cancelled->loan->fresh()->status,
        ], 'Funding transaction cancelled.');
    }

    // ─── View Loan Funding Details ───────────────────────────────────

    public function loanFundings(Request $request, Loan $loan): JsonResponse
    {
        // Anyone with marketplace view can see funding details
        $fundings = $this->fundingService->getLoanFundings($loan->id);
        $summary = $this->fundingService->getLoanFundingSummary($loan->id);

        // Anonymize lender names for public view
        $anonymizedFundings = $fundings->map(function ($funding) {
            $lender = $funding->lender;

            return [
                'id' => $funding->id,
                'amount' => $funding->amount,
                'status' => $funding->status,
                'created_at' => $funding->created_at->toIso8601String(),
                'confirmed_at' => $funding->confirmed_at?->toIso8601String(),
                'lender_hash' => substr(md5($lender->id), 0, 8),
            ];
        });

        return $this->success([
            'summary' => $summary,
            'fundings' => $anonymizedFundings,
        ], 'Loan funding details retrieved.');
    }
}
