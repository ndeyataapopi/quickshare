<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Traits\HasDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoanFundedNotification extends Notification
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
            ->subject('Loan Fully Funded - QuickShare')
            ->greeting("Hello {$notifiable->first_name},")
            ->line("Great news! Your loan application {$reference} has been fully funded!")
            ->line("Loan Amount: {$currency} {$amount}")
            ->line('Your loan is now being processed for disbursement.')
            ->action('View Loan Status', $loanId ? route('client.loans.show', $loanId) : route('client.loans.index'))
            ->line('You will receive another notification once the funds are disbursed.');
    }
}
