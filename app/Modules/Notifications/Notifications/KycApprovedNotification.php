<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Traits\HasDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KycApprovedNotification extends Notification
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
        return (new MailMessage)
            ->subject('KYC Approved - QuickShare')
            ->greeting("Hello {$notifiable->first_name},")
            ->line('Great news! Your KYC verification has been approved.')
            ->line('You can now apply for loans and access all features of QuickShare.')
            ->action('Apply for a Loan', route('client.loans.create'))
            ->line('Thank you for using QuickShare!');
    }
}
