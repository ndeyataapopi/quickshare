<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Traits\HasDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DisbursementRejectedNotification extends Notification
{
    use HasDatabaseChannel, Queueable;

    public function __construct(
        protected array $data,
        protected string $via,
    ) {
    }

    public function via($notifiable): array
    {
        return [$this->via];
    }

    public function toMail($notifiable): MailMessage
    {
        $reference = $this->data['reference'] ?? 'N/A';
        $amount = $this->data['amount'] ?? '0';
        $reason = $this->data['reason'] ?? 'No reason provided.';
        $currency = config('loans.currency_symbol', 'N$');
        $loanId = $this->data['loan_id'] ?? null;

        return (new MailMessage)
            ->subject('Disbursement Rejected by Borrower - QuickShare')
            ->greeting("Hello {$notifiable->first_name},")
            ->line("The borrower has rejected the disbursement for loan {$reference}.")
            ->line("Amount: {$currency} {$amount}")
            ->line("Reason: {$reason}")
            ->line('Please review the disbursement and re-initiate if necessary.')
            ->action('View Disbursement', $loanId ? route('admin.disbursements.show', $loanId) : route('admin.disbursements.index'));
    }
}
