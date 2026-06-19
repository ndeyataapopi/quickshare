<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Traits\HasDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RepaymentReminderNotification extends Notification
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
        $dueDate = $this->data['due_date'] ?? 'soon';
        $loanRef = $this->data['reference'] ?? 'N/A';

        return (new MailMessage)
            ->subject('Repayment Reminder - QuickShare')
            ->greeting("Hello {$notifiable->first_name},")
            ->line("This is a friendly reminder that your loan repayment is due soon.")
            ->line("Loan: {$loanRef}")
            ->line("Amount Due: R{$amount}")
            ->line("Due Date: {$dueDate}")
            ->action('Make Payment', url('/repayments'))
            ->line('Please ensure sufficient funds are available in your account.');
    }
}
