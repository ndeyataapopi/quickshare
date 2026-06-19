<?php

namespace App\Modules\Loans\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Requests\ApproveLoanRequest;
use App\Modules\Loans\Requests\RejectLoanRequest;
use App\Modules\Loans\Services\LoanService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class LoanAdminController extends Controller
{
    use ApiResponse;

    public function __construct(protected LoanService $loanService)
    {
    }

    public function index(): JsonResponse
    {
        $loans = $this->loanService->getPendingReviewLoans();

        return $this->paginated($loans, 'Pending loans retrieved.');
    }

    public function show(Loan $loan): JsonResponse
    {
        $loan->load([
            'borrower:id,first_name,last_name,email,phone,trust_score',
            'reviewer:id,first_name,last_name',
        ]);

        return $this->success(['loan' => $loan]);
    }

    public function approve(ApproveLoanRequest $request, Loan $loan): JsonResponse
    {
        $loan = $this->loanService->approve(
            $loan,
            $request->user(),
            $request->approved_amount,
            $request->admin_notes,
        );

        return $this->success(['loan' => $loan], 'Loan approved and listed on marketplace.');
    }

    public function reject(RejectLoanRequest $request, Loan $loan): JsonResponse
    {
        $loan = $this->loanService->reject(
            $loan,
            $request->user(),
            $request->reason,
        );

        return $this->success(['loan' => $loan], 'Loan rejected.');
    }

    public function marketplace(): JsonResponse
    {
        $loans = $this->loanService->getMarketplaceLoans();

        return $this->paginated($loans, 'Marketplace loans retrieved.');
    }
}
