<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Traits\HasDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetNotification extends Notification
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
        $code = $this->data['code'] ?? '000000';
        $expiresIn = $this->data['expires_in'] ?? '15 minutes';

        return (new MailMessage)
            ->subject('Password Reset - QuickShare')
            ->greeting("Hello {$notifiable->first_name},")
            ->line('You have requested a password reset.')
            ->line("Your reset code is: **{$code}**")
            ->line("This code will expire in {$expiresIn}.")
            ->line('If you did not request this, please ignore this email or contact support.');
    }
}
