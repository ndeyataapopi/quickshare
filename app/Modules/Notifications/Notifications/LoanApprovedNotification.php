<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Traits\HasDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoanApprovedNotification extends Notification
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
        $loanId = $this->data['loan_id'] ?? null;

        return (new MailMessage)
            ->subject('Loan Application Approved - QuickShare')
            ->greeting("Hello {$notifiable->first_name},")
            ->line('Congratulations! Your loan application has been approved.')
            ->line('Your loan will be listed on the marketplace for lenders to fund.')
            ->line('You will be notified when your loan is fully funded and disbursed.')
            ->action('View Loan', $loanId ? route('client.loans.show', $loanId) : route('client.loans.index'))
            ->line('Thank you for choosing QuickShare!');
    }
}
