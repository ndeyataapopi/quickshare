<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Traits\HasDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification
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
            ->subject('Welcome to QuickShare!')
            ->greeting("Hello {$notifiable->first_name},")
            ->line('Welcome to QuickShare - your peer-to-peer lending platform!')
            ->line('Here is what you can do:')
            ->line('• Apply for personal loans')
            ->line('• Fund loans and earn returns')
            ->line('• Build your trust score')
            ->action('Complete Your Profile', url('/profile'))
            ->line('If you have any questions, our support team is here to help.');
    }
}
