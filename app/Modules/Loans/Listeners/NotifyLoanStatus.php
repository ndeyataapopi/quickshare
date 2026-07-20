<?php

namespace App\Modules\Loans\Listeners;

use App\Models\ActivityLog;
use App\Modules\Loans\Events\LoanApproved;
use App\Modules\Loans\Events\LoanRejected;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyLoanStatus implements ShouldQueue
{
    public function __construct(
        protected NotificationService $notificationService,
    ) {
    }

    public function handle(LoanApproved|LoanRejected $event): void
    {
        $status = $event instanceof LoanApproved ? 'approved' : 'rejected';

        ActivityLog::create([
            'user_id' => $event->borrower->id,
            'action' => "loan.{$status}",
            'description' => "Loan {$status}",
            'subject_type' => get_class($event->borrower),
            'subject_id' => $event->borrower->id,
            'metadata' => array_filter([
                'loan_id' => $event->loanId,
                'reason' => $event instanceof LoanRejected ? $event->reason : null,
            ]),
            'ip_address' => request()->ip(),
        ]);

        // Loan approval/rejection notifications via email
        $loan = \App\Modules\Loans\Models\Loan::find($event->loanId);

        $notificationType = $event instanceof LoanApproved ? 'loan_approved' : 'loan_rejected';
        $data = [
            'loan_id' => $event->loanId,
            'reference' => $loan?->reference ?? 'N/A',
            'amount' => $loan?->approved_amount ?? $loan?->requested_amount ?? 0,
        ];

        if ($event instanceof LoanRejected) {
            $data['reason'] = $event->reason;
        }

        $this->notificationService->send(
            $event->borrower,
            $notificationType,
            $data,
            ['email', 'database']
        );
    }
}
