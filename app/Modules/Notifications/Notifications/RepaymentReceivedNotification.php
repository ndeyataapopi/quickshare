<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Traits\HasDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RepaymentReceivedNotification extends Notification
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
        $amount = $this->data['amount'] ?? '0';
        $loanRef = $this->data['reference'] ?? 'N/A';
        $balance = $this->data['remaining_balance'] ?? '0';

        return (new MailMessage)
            ->subject('Payment Received - QuickShare')
            ->greeting("Hello {$notifiable->first_name},")
            ->line('Thank you for your payment!')
            ->line("Amount Received: R{$amount}")
            ->line("Loan: {$loanRef}")
            ->line("Remaining Balance: R{$balance}")
            ->action('View Loan', url("/loans/{$this->data['loan_id']}"))
            ->line('Your trust score has been positively updated.')
            ->line('Thank you for being a reliable borrower!');
    }
}
