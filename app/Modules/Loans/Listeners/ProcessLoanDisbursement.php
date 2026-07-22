<?php

namespace App\Modules\Loans\Listeners;

use App\Models\ActivityLog;
use App\Modules\Loans\Events\LoanDisbursed;
use App\Modules\Loans\Jobs\ProcessDisbursementJob;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Services\DisbursementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class ProcessLoanDisbursement implements ShouldQueue
{
    public function handle(LoanDisbursed $event): void
    {
        ActivityLog::create([
            'user_id' => null,
            'action' => 'loan.disbursed',
            'description' => "Loan #{$event->loanId} disbursed: R{$event->amount}",
            'metadata' => [
                'loan_id' => $event->loanId,
                'amount' => $event->amount,
            ],
            'ip_address' => request()?->ip(),
        ]);

        // Get the loan
        $loan = Loan::find($event->loanId);

        if (! $loan) {
            Log::error("ProcessLoanDisbursement: Loan {$event->loanId} not found");
            return;
        }

        // Initiate disbursement via service
        $service = app(DisbursementService::class);

        try {
            $transaction = $service->initiateDisbursement($loan);

            // Dispatch async job to process the disbursement
            ProcessDisbursementJob::dispatch($transaction->id);

            Log::info("ProcessLoanDisbursement: Disbursement initiated for loan {$event->loanId}", [
                'disbursement_id' => $transaction->id,
                'reference' => $transaction->transaction_reference,
            ]);
        } catch (\Throwable $e) {
            Log::error("ProcessLoanDisbursement: Failed to initiate disbursement for loan {$event->loanId}", [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
