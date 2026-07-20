<?php

namespace App\Modules\Repayments\Jobs;

use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Repayments\Models\Repayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendRepaymentRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 600];

    // How many days before due date to send reminder
    protected array $reminderDays = [7, 3, 1];

    public function handle(NotificationService $notificationService): void
    {
        Log::info('SendRepaymentRemindersJob: Starting reminder dispatch');

        $sent = 0;

        foreach ($this->reminderDays as $daysAhead) {
            $dueDate = now()->addDays($daysAhead)->toDateString();

            $repayments = Repayment::with(['loan.borrower'])
                ->where('status', 'pending')
                ->whereDate('due_date', $dueDate)
                ->get();

            foreach ($repayments as $repayment) {
                try {
                    $notificationService->send(
                        $repayment->loan->borrower,
                        'repayment_reminder',
                        [
                            'loan_id' => $repayment->loan_id,
                            'reference' => $repayment->loan->reference,
                            'amount' => $repayment->amount,
                            'due_date' => $repayment->due_date->toFormattedDateString(),
                            'days_until_due' => $daysAhead,
                        ],
                        ['email', 'sms', 'database']
                    );

                    $sent++;
                } catch (\Throwable $e) {
                    Log::error('SendRepaymentRemindersJob: Failed to send reminder', [
                        'repayment_id' => $repayment->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('SendRepaymentRemindersJob: Completed', ['reminders_sent' => $sent]);
    }
}
