<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Traits\HasDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FundingPaymentSubmittedNotification extends Notification
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
        $transactionId = $this->data['transaction_id'] ?? null;

        return (new MailMessage)
            ->subject('Funding Payment Submitted - QuickShare')
            ->greeting("Hello Admin,")
            ->line("A funding payment has been submitted for loan {$reference}.")
            ->line("Amount: {$currency} {$amount}")
            ->line('Please review the proof of payment and verify the transaction.')
            ->action('Review Funding', $transactionId ? route('admin.funding-payments.show', $transactionId) : url('/'));
    }
}
