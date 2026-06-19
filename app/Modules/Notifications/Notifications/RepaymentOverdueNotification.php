<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Traits\HasDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RepaymentOverdueNotification extends Notification
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
        $daysOverdue = $this->data['days_overdue'] ?? 0;
        $loanRef = $this->data['reference'] ?? 'N/A';
        $penalty = $this->data['penalty'] ?? '0';

        return (new MailMessage)
            ->subject('URGENT: Overdue Repayment - QuickShare')
            ->greeting("Hello {$notifiable->first_name},")
            ->line("Your loan repayment is now OVERDUE.")
            ->line("Loan: {$loanRef}")
            ->line("Amount Due: R{$amount}")
            ->line("Days Overdue: {$daysOverdue}")
            ->line("Penalty Accrued: R{$penalty}")
            ->action('Pay Now', url('/repayments'))
            ->line('Please make payment immediately to avoid additional fees and negative impact on your credit score.')
            ->line('If you are experiencing difficulties, please contact our support team.');
    }
}
