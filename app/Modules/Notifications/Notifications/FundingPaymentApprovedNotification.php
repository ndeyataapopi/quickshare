<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Traits\HasDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FundingPaymentApprovedNotification extends Notification
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
        $currency = config('loans.currency_symbol', 'N$');
        $loanId = $this->data['loan_id'] ?? null;

        return (new MailMessage)
            ->subject('Funding Payment Approved - QuickShare')
            ->greeting("Hello {$notifiable->first_name},")
            ->line("Your funding payment of {$currency} {$amount} for loan {$reference} has been approved.")
            ->line('Your investment is now confirmed.')
            ->action('View Investment', $loanId ? route('client.loans.show', $loanId) : route('client.investments.index'));
    }
}
