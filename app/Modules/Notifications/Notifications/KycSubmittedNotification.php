<?php

namespace App\Modules\Notifications\Notifications;

use App\Modules\Notifications\Traits\HasDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KycSubmittedNotification extends Notification
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
        $userName = $this->data['user_name'] ?? 'Unknown User';
        $userEmail = $this->data['user_email'] ?? 'unknown@email.com';
        $documentType = $this->data['document_type'] ?? 'documents';

        return (new MailMessage)
            ->subject('New KYC Submission - ' . $userName)
            ->greeting("Hello {$notifiable->first_name},")
            ->line("A new KYC submission has been received from:")
            ->line("**Name:** {$userName}")
            ->line("**Email:** {$userEmail}")
            ->line("**Document Type:** {$documentType}")
            ->line("Please review the submission in the admin panel.")
            ->action('Review KYC', url('/admin/kyc'))
            ->line('Thank you for your prompt attention to this matter.');
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => 'New KYC Submission',
            'message' => "KYC documents submitted by {$this->data['user_name']}",
            'user_id' => $this->data['user_id'],
            'user_name' => $this->data['user_name'],
            'user_email' => $this->data['user_email'],
            'document_type' => $this->data['document_type'],
            'action_url' => url('/admin/kyc'),
        ];
    }
}
