<?php

namespace App\Modules\Loans\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Loans\DTOs\AffordabilityInput;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Services\AffordabilityService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AffordabilityController extends Controller
{
    use ApiResponse;

    public function __construct(protected AffordabilityService $affordabilityService)
    {
    }

    public function assess(Request $request): JsonResponse
    {
        $request->validate([
            'monthly_income' => ['required', 'numeric', 'min:0'],
            'monthly_expenses' => ['sometimes', 'numeric', 'min:0'],
            'existing_debt' => ['sometimes', 'numeric', 'min:0'],
            'monthly_debt_repayments' => ['sometimes', 'numeric', 'min:0'],
            'payslip_gross' => ['sometimes', 'numeric', 'min:0'],
            'payslip_net' => ['sometimes', 'numeric', 'min:0'],
            'bank_avg_balance' => ['sometimes', 'numeric', 'min:0'],
            'bank_avg_income' => ['sometimes', 'numeric', 'min:0'],
            'bank_avg_expenses' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $input = AffordabilityInput::fromArray($request->all());
        $assessment = $this->affordabilityService->assess($request->user(), $input);

        return $this->success([
            'assessment' => $assessment,
        ], 'Affordability assessment completed.');
    }

    public function assessForLoan(Request $request, Loan $loan): JsonResponse
    {
        $request->validate([
            'monthly_income' => ['required', 'numeric', 'min:0'],
            'monthly_expenses' => ['sometimes', 'numeric', 'min:0'],
            'existing_debt' => ['sometimes', 'numeric', 'min:0'],
            'monthly_debt_repayments' => ['sometimes', 'numeric', 'min:0'],
            'payslip_gross' => ['sometimes', 'numeric', 'min:0'],
            'payslip_net' => ['sometimes', 'numeric', 'min:0'],
            'bank_avg_balance' => ['sometimes', 'numeric', 'min:0'],
            'bank_avg_income' => ['sometimes', 'numeric', 'min:0'],
            'bank_avg_expenses' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $input = AffordabilityInput::fromArray($request->all());
        $assessment = $this->affordabilityService->approveOrRejectLoan($loan, $input);

        return $this->success([
            'assessment' => $assessment,
        ], 'Loan affordability assessment completed.');
    }

    public function maxLoan(Request $request): JsonResponse
    {
        $request->validate([
            'monthly_income' => ['required', 'numeric', 'min:0'],
            'monthly_expenses' => ['sometimes', 'numeric', 'min:0'],
            'monthly_debt_repayments' => ['sometimes', 'numeric', 'min:0'],
            'bank_avg_income' => ['sometimes', 'numeric', 'min:0'],
            'bank_avg_expenses' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $input = AffordabilityInput::fromArray($request->all());
        $disposable = $this->affordabilityService->calculateDisposableIncome($input);
        $maxLoan = $this->affordabilityService->calculateMaxLoan($request->user(), $disposable);
        $dti = $this->affordabilityService->calculateDTI($input);

        return $this->success([
            'max_loan_amount' => round($maxLoan, 2),
            'disposable_income' => round($disposable, 2),
            'max_monthly_repayment' => $this->affordabilityService->calculateMaxMonthlyRepayment($disposable),
            'debt_to_income_ratio' => $dti,
            'dti_classification' => $this->affordabilityService->classifyDTI($dti),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $history = $this->affordabilityService->getAssessmentHistory(
            $request->user(),
            $request->integer('limit', 10),
        );

        return $this->success(['assessments' => $history]);
    }
}
