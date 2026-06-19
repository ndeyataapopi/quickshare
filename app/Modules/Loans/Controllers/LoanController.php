<?php

namespace App\Modules\Loans\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Loans\DTOs\LoanRequestData;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Requests\RequestLoanRequest;
use App\Modules\Loans\Services\LoanService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    use ApiResponse;

    public function __construct(protected LoanService $loanService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $loans = $this->loanService->getBorrowerLoans($request->user());

        return $this->paginated($loans, 'Loans retrieved.');
    }

    public function show(Loan $loan, Request $request): JsonResponse
    {
        if ($loan->borrower_id !== $request->user()->id) {
            return $this->error('Unauthorized.', 403);
        }

        return $this->success(['loan' => $loan]);
    }

    public function store(RequestLoanRequest $request): JsonResponse
    {
        $data = LoanRequestData::fromArray([
            'borrower_id' => $request->user()->id,
            'requested_amount' => $request->requested_amount,
            'loan_term_days' => $request->loan_term_days,
            'purpose' => $request->purpose,
        ]);

        $loan = $this->loanService->requestLoan($data);

        return $this->created(['loan' => $loan], 'Loan request submitted for review.');
    }

    public function cancel(Loan $loan, Request $request): JsonResponse
    {
        $loan = $this->loanService->cancel($loan, $request->user());

        return $this->success(['loan' => $loan], 'Loan cancelled.');
    }

    public function calculate(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:500'],
            'term_days' => ['required', 'integer', 'min:30', 'max:365'],
        ]);

        $calculation = $this->loanService->calculate(
            $request->user(),
            (float) $request->amount,
            (int) $request->term_days,
        );

        return $this->success($calculation->toArray(), 'Loan calculation.');
    }
}
