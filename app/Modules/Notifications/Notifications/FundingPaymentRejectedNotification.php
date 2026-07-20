<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Traits\HasDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FundingPaymentRejectedNotification extends Notification
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
            ->subject('Funding Payment Rejected - QuickShare')
            ->greeting("Hello {$notifiable->first_name},")
            ->line("Your funding payment of {$currency} {$amount} for loan {$reference} was rejected.")
            ->line("Reason: {$reason}")
            ->line('If you believe this is an error, please contact support.')
            ->action('View Loan', $loanId ? route('client.loans.show', $loanId) : route('client.marketplace.index'));
    }
}
