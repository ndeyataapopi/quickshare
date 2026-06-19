<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Traits\HasDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KycRejectedNotification extends Notification
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
        $reason = $this->data['reason'] ?? 'Please review your submission and try again.';

        return (new MailMessage)
            ->subject('KYC Verification Update - QuickShare')
            ->greeting("Hello {$notifiable->first_name},")
            ->line('We have reviewed your KYC submission.')
            ->line('Unfortunately, your verification was not approved.')
            ->line("Reason: {$reason}")
            ->action('Resubmit KYC', url('/kyc/resubmit'))
            ->line('If you need assistance, please contact our support team.');
    }
}
