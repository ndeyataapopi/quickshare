<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Traits\HasDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoanSubmittedNotification extends Notification
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
        $amount    = $this->data['amount'] ?? '0';
        $loanId    = $this->data['loan_id'] ?? null;
        $currency  = config('loans.currency_symbol', 'N$');

        return (new MailMessage)
            ->subject('Loan Application Submitted - QuickShare')
            ->greeting("Hello {$notifiable->first_name},")
            ->line('Your loan application has been received and is under review.')
            ->line("Loan Reference: {$reference}")
            ->line("Amount: {$currency} {$amount}")
            ->action('View Application', $loanId ? route('client.loans.show', $loanId) : route('client.loans.index'))
            ->line('We will notify you once the review is complete.');
    }
}
