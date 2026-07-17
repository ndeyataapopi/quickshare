<?php

namespace App\Modules\Loans\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Loans\DTOs\LoanRequestData;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Requests\RequestLoanRequest;
use App\Modules\Loans\Services\LoanService;
use App\Modules\Loans\Services\TrustTierService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LoanController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected LoanService $loanService,
        protected TrustTierService $trustTierService,
    ) {}

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
            'agreement_read' => $request->boolean('agreement_read'),
            'agreement_terms' => $request->boolean('agreement_terms'),
            'electronic_documents' => $request->boolean('electronic_documents'),
            'agreement_version' => $request->agreement_version,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
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
        $tier = $this->trustTierService->forScore((float) $request->user()->trust_score);
        $minimumAmount = (float) config('loans.min_amount');

        $request->validate([
            'amount' => ['required', 'numeric', "min:{$minimumAmount}", "max:{$tier['maximum_loan']}"],
            'term_days' => ['required', 'integer', Rule::in($tier['allowed_durations'])],
        ]);

        $calculation = $this->loanService->calculate(
            $request->user(),
            (float) $request->amount,
            (int) $request->term_days,
        );

        return $this->success($calculation->toArray(), 'Loan calculation.');
    }
}
