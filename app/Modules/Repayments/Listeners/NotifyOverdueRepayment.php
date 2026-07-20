<?php

namespace App\Modules\Repayments\Listeners;

use App\Models\ActivityLog;
use App\Modules\Loans\Models\Loan;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Repayments\Events\RepaymentOverdue;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyOverdueRepayment implements ShouldQueue
{
    public function __construct(
        protected NotificationService $notificationService,
    ) {
    }

    public function handle(RepaymentOverdue $event): void
    {
        ActivityLog::create([
            'user_id' => $event->borrower->id,
            'action' => 'repayment.overdue',
            'description' => "Repayment overdue by {$event->daysOverdue} days on loan #{$event->loanId}",
            'subject_type' => get_class($event->borrower),
            'subject_id' => $event->borrower->id,
            'metadata' => [
                'loan_id' => $event->loanId,
                'days_overdue' => $event->daysOverdue,
            ],
            'ip_address' => request()->ip(),
        ]);

        // Send overdue notification to borrower via multiple channels
        $loan = Loan::find($event->loanId);

        $this->notificationService->send(
            $event->borrower,
            'repayment_overdue',
            [
                'loan_id' => $event->loanId,
                'reference' => $loan?->reference ?? 'N/A',
                'days_overdue' => $event->daysOverdue,
                'amount' => $loan?->outstanding_balance ?? 0,
            ],
            ['email', 'database', 'sms', 'whatsapp']
        );
    }
}
