<?php

namespace App\Modules\Collections\Jobs;

use App\Models\User;
use App\Modules\Repayments\Models\Repayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotifyReferrerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public User $referrer,
        public User $borrower,
        public Repayment $repayment,
    ) {
    }

    public function handle(): void
    {
        try {
            // In production, send actual email/SMS to referrer
            // For now, just log the notification

            Log::info('Referrer notified about overdue repayment', [
                'referrer_id' => $this->referrer->id,
                'referrer_email' => $this->referrer->email,
                'borrower_id' => $this->borrower->id,
                'borrower_email' => $this->borrower->email,
                'repayment_id' => $this->repayment->id,
                'amount' => $this->repayment->amount,
                'days_overdue' => $this->repayment->days_overdue,
            ]);

            // Example: Mail::to($this->referrer)->send(new ReferrerOverdueNotification(...));

        } catch (\Throwable $e) {
            Log::error('Failed to notify referrer', [
                'referrer_id' => $this->referrer->id,
                'borrower_id' => $this->borrower->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
