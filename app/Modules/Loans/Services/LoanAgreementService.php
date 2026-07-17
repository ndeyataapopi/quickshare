<?php

namespace App\Modules\Loans\Services;

use App\Models\User;
use App\Modules\Loans\DTOs\LoanCalculation;
use App\Modules\Loans\Models\Loan;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;

class LoanAgreementService
{
    public function __construct(
        protected TrustTierService $trustTierService,
    ) {}

    public function generate(Loan $loan, LoanCalculation $calculation, CarbonInterface $repaymentDate): string
    {
        $path = 'loan-agreements/'.$loan->reference.'.pdf';
        $version = (string) config('loan.agreement.version');
        $disk = (string) config('loan.agreement.disk');
        $pdf = Pdf::loadView('pdf.loan-agreement', $this->data($loan, $calculation, $repaymentDate));

        Storage::disk($disk)->put($path, $pdf->output());

        $loan->update([
            'agreement_path' => $path,
            'agreement_version' => $version,
            'agreement_generated_at' => now(),
        ]);

        return $path;
    }

    public function preview(User $borrower, LoanCalculation $calculation, CarbonInterface $repaymentDate): string
    {
        $loan = new Loan(['reference' => 'PREVIEW']);
        $loan->setRelation('borrower', $borrower);

        return Pdf::loadView('pdf.loan-agreement', $this->data($loan, $calculation, $repaymentDate))->output();
    }

    public function data(Loan $loan, LoanCalculation $calculation, CarbonInterface $repaymentDate): array
    {
        $tier = $this->trustTierService->forScore($calculation->riskScore);

        return [
            'loan' => $loan,
            'borrower' => $loan->borrower,
            'calculation' => $calculation,
            'lenderReturnPercent' => $tier['lender_return_percent'],
            'lenderReturnAmount' => round(
                $calculation->principal * ($tier['lender_return_percent'] / 100) * ($calculation->termDays / 365),
                2,
            ),
            'repaymentDate' => $repaymentDate,
            'terms' => config('loan.agreement.terms'),
            'conditions' => config('loan.agreement.conditions'),
            'agreementVersion' => (string) config('loan.agreement.version'),
        ];
    }
}
