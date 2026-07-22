<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Traits\HasDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RepaymentRejectedNotification extends Notification
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
        $reason = $this->data['reason'] ?? 'No reason provided';

        return (new MailMessage)
            ->subject('Repayment Rejected - QuickShare')
            ->greeting("Hello {$notifiable->first_name},")
            ->line('Your repayment submission was not approved.')
            ->line("Amount: N$ {$amount}")
            ->line("Loan: {$loanRef}")
            ->line("Reason: {$reason}")
            ->action('View Repayments', url('/repayments'))
            ->line('Please contact support if you have questions.');
    }
}
