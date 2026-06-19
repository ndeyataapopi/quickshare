<?php

namespace App\Modules\Notifications\Traits;

trait HasDatabaseChannel
{
    public function toDatabase($notifiable): array
    {
        return [
            'type' => $this->notificationType(),
            'message' => $this->databaseMessage($notifiable),
            'data' => $this->data ?? [],
        ];
    }

    protected function notificationType(): string
    {
        return strtolower(
            preg_replace('/Notification$/', '', class_basename(static::class))
        );
    }

    protected function databaseMessage($notifiable): string
    {
        $type = $this->notificationType();

        $messages = [
            'kyc_approved' => 'Your KYC has been approved.',
            'kyc_rejected' => 'Your KYC was rejected.',
            'loan_approved' => 'Your loan application has been approved.',
            'loan_rejected' => 'Your loan application was rejected.',
            'loan_funded' => 'Your loan is now fully funded.',
            'loan_disbursed' => 'Your loan funds have been disbursed.',
            'repayment_reminder' => 'You have an upcoming repayment due.',
            'repayment_overdue' => 'Your repayment is overdue.',
            'repayment_received' => 'Your repayment was received.',
            'welcome' => 'Welcome to QuickShare!',
            'password_reset' => 'A password reset was requested.',
        ];

        return $messages[$type] ?? 'You have a new notification.';
    }
}
