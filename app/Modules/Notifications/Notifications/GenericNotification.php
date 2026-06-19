<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Traits\HasDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GenericNotification extends Notification
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
        $subject = $this->data['subject'] ?? 'QuickShare Notification';
        $message = $this->data['message'] ?? 'You have a new notification.';
        $actionText = $this->data['action_text'] ?? 'View Details';
        $actionUrl = $this->data['action_url'] ?? url('/');

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->first_name},")
            ->line($message)
            ->action($actionText, $actionUrl);
    }
}
