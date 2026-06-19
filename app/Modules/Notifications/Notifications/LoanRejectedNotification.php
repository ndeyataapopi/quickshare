<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Traits\HasDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoanRejectedNotification extends Notification
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
        $reason = $this->data['reason'] ?? 'Your application did not meet our current criteria.';

        return (new MailMessage)
            ->subject('Loan Application Update - QuickShare')
            ->greeting("Hello {$notifiable->first_name},")
            ->line('We have reviewed your loan application.')
            ->line('Unfortunately, your application was not approved at this time.')
            ->line("Reason: {$reason}")
            ->action('Apply Again', route('client.loans.create'))
            ->line('You may reapply once you have improved your trust score or resolved the issue mentioned above.')
            ->line('If you have questions, please contact our support team.');
    }
}
