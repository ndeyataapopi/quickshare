<?php

namespace App\Modules\Loans\Jobs;

use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Services\LoanAgreementService;
use App\Modules\Loans\Services\LoanService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateLoanAgreementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $loanId,
    ) {}

    public function handle(LoanService $loanService, LoanAgreementService $agreementService): void
    {
        $loan = Loan::with('borrower')->find($this->loanId);

        if (! $loan) {
            Log::warning("GenerateLoanAgreementJob: Loan {$this->loanId} not found");
            return;
        }

        if ($loan->agreement_path !== null) {
            Log::info("GenerateLoanAgreementJob: Loan {$this->loanId} already has an agreement, skipping");
            return;
        }

        $borrower = $loan->borrower;

        if (! $borrower) {
            Log::warning("GenerateLoanAgreementJob: Loan {$this->loanId} has no borrower");
            return;
        }

        $principal = $loanService->loanPrincipal($loan);
        $calculation = $loanService->calculate($borrower, $principal, $loan->loan_term_days);
        $repaymentDate = Carbon::parse($loan->repayment_date);

        $agreementService->generate($loan, $calculation, $repaymentDate);

        Log::info("GenerateLoanAgreementJob: Agreement generated for loan {$this->loanId}");
    }
}
