<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Traits\HasDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FundingPaymentInfoRequestedNotification extends Notification
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
        $message = $this->data['message'] ?? 'Please provide additional information.';
        $currency = config('loans.currency_symbol', 'N$');
        $transactionId = $this->data['transaction_id'] ?? null;

        return (new MailMessage)
            ->subject('Additional Payment Information Required - QuickShare')
            ->greeting("Hello {$notifiable->first_name},")
            ->line("We need more information for your funding payment of {$currency} {$amount} on loan {$reference}.")
            ->line("Message: {$message}")
            ->action('Update Payment Details', $transactionId ? route('client.funding.payment', $transactionId) : route('client.investments.index'));
    }
}
