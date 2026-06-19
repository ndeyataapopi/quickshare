<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Traits\HasDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoanDisbursedNotification extends Notification
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
        $reference        = $this->data['reference'] ?? 'N/A';
        $amount           = $this->data['amount'] ?? '0';
        $loanId           = $this->data['loan_id'] ?? null;
        $disbursementDate = $this->data['disbursed_at'] ?? now()->toDateString();
        $currency         = config('loans.currency_symbol', 'N$');

        return (new MailMessage)
            ->subject('Funds Disbursed - QuickShare')
            ->greeting("Hello {$notifiable->first_name},")
            ->line("Your loan {$reference} has been disbursed!")
            ->line("Amount: {$currency} {$amount}")
            ->line("Disbursement Date: {$disbursementDate}")
            ->line('The funds should appear in your bank account within 1-2 business days.')
            ->action('View Repayment Schedule', $loanId ? route('client.repayments.index') : route('client.dashboard'))
            ->line('Thank you for choosing QuickShare!');
    }
}
