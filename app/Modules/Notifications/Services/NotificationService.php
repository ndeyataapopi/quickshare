<?php

namespace App\Modules\Notifications\Services;

use App\Models\User;
use App\Modules\Notifications\Channels\SmsChannel;
use App\Modules\Notifications\Channels\WhatsAppChannel;
use App\Modules\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    // ─── Channel Configuration ─────────────────────────────────────────

    const CHANNELS = [
        'email' => 'mail',
        'sms' => SmsChannel::class,
        'whatsapp' => WhatsAppChannel::class,
    ];

    // ─── Send Notification ───────────────────────────────────────────

    public function send(
        User $user,
        string $type,
        array $data,
        array $channels = ['email'],
    ): array {
        $results = [];

        foreach ($channels as $channel) {
            try {
                $result = $this->sendViaChannel($user, $type, $data, $channel);
                $results[$channel] = $result;

                // Fire event for logging
                NotificationSent::dispatch($user, $channel, $type);

                Log::info('Notification sent', [
                    'user_id' => $user->id,
                    'channel' => $channel,
                    'type' => $type,
                    'success' => $result['success'] ?? true,
                ]);
            } catch (\Throwable $e) {
                $results[$channel] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];

                Log::error('Notification failed', [
                    'user_id' => $user->id,
                    'channel' => $channel,
                    'type' => $type,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    // ─── Send Via Specific Channel ───────────────────────────────────

    protected function sendViaChannel(
        User $user,
        string $type,
        array $data,
        string $channel,
    ): array {
        return match ($channel) {
            'email'    => $this->sendEmail($user, $type, $data),
            'database' => $this->sendDatabase($user, $type, $data),
            'sms'      => $this->sendSms($user, $type, $data),
            'whatsapp' => $this->sendWhatsApp($user, $type, $data),
            default    => ['success' => false, 'error' => 'Unknown channel'],
        };
    }

    // ─── Email Channel ───────────────────────────────────────────────

    protected function sendEmail(User $user, string $type, array $data): array
    {
        // Laravel mail channel identifier is 'mail'
        $notification = $this->createNotification($type, $data, 'mail');
        $user->notify($notification);

        return [
            'success' => true,
            'channel' => 'email',
            'message_id' => null,
        ];
    }

    // ─── Database Channel ────────────────────────────────────────────

    protected function sendDatabase(User $user, string $type, array $data): array
    {
        // Laravel database channel persists to notifications table
        $notification = $this->createNotification($type, $data, 'database');
        $user->notify($notification);

        return [
            'success' => true,
            'channel' => 'database',
        ];
    }

    // ─── SMS Channel ─────────────────────────────────────────────────

    protected function sendSms(User $user, string $type, array $data): array
    {
        // Delegate to SMS channel
        $smsChannel = new SmsChannel();
        return $smsChannel->send($user, $type, $data);
    }

    // ─── WhatsApp Channel ────────────────────────────────────────────

    protected function sendWhatsApp(User $user, string $type, array $data): array
    {
        // Delegate to WhatsApp channel
        $waChannel = new WhatsAppChannel();
        return $waChannel->send($user, $type, $data);
    }

    // ─── Create Notification Instance ────────────────────────────────

    protected function createNotification(string $type, array $data, string $channel): Notification
    {
        $notificationClass = $this->getNotificationClass($type);

        if (! class_exists($notificationClass)) {
            throw new \InvalidArgumentException("Notification class not found: {$notificationClass}");
        }

        return new $notificationClass($data, $channel);
    }

    // ─── Get Notification Class ──────────────────────────────────────

    protected function getNotificationClass(string $type): string
    {
        $classes = [
            'kyc_submitted' => \App\Modules\Notifications\Notifications\KycSubmittedNotification::class,
            'kyc_approved' => \App\Modules\Notifications\Notifications\KycApprovedNotification::class,
            'kyc_rejected' => \App\Modules\Notifications\Notifications\KycRejectedNotification::class,
            'loan_submitted' => \App\Modules\Notifications\Notifications\LoanSubmittedNotification::class,
            'loan_approved' => \App\Modules\Notifications\Notifications\LoanApprovedNotification::class,
            'loan_rejected' => \App\Modules\Notifications\Notifications\LoanRejectedNotification::class,
            'loan_funded' => \App\Modules\Notifications\Notifications\LoanFundedNotification::class,
            'loan_disbursed' => \App\Modules\Notifications\Notifications\LoanDisbursedNotification::class,
            'repayment_reminder' => \App\Modules\Notifications\Notifications\RepaymentReminderNotification::class,
            'repayment_overdue' => \App\Modules\Notifications\Notifications\RepaymentOverdueNotification::class,
            'repayment_received' => \App\Modules\Notifications\Notifications\RepaymentReceivedNotification::class,
            'welcome' => \App\Modules\Notifications\Notifications\WelcomeNotification::class,
            'password_reset' => \App\Modules\Notifications\Notifications\PasswordResetNotification::class,
            'funding_payment_submitted' => \App\Modules\Notifications\Notifications\FundingPaymentSubmittedNotification::class,
            'funding_payment_approved' => \App\Modules\Notifications\Notifications\FundingPaymentApprovedNotification::class,
            'funding_payment_rejected' => \App\Modules\Notifications\Notifications\FundingPaymentRejectedNotification::class,
            'funding_payment_info_requested' => \App\Modules\Notifications\Notifications\FundingPaymentInfoRequestedNotification::class,
        ];

        return $classes[$type] ?? \App\Modules\Notifications\Notifications\GenericNotification::class;
    }

    // ─── Determine Channels for User ─────────────────────────────────

    public function determineChannels(User $user, string $type): array
    {
        // Get from config, fallback to email
        $channels = config("notifications.channels.{$type}", ['email']);

        // Check user preferences (would be stored in DB)
        // if ($user->notification_preferences) {
        //     $channels = $user->notification_preferences[$type] ?? $channels;
        // }

        return $channels;
    }

    // ─── Send with Auto Channel Resolution ───────────────────────────

    public function sendAuto(User $user, string $type, array $data): array
    {
        $channels = $this->determineChannels($user, $type);
        return $this->send($user, $type, $data, $channels);
    }

    // ─── Send to Multiple Users ──────────────────────────────────────

    public function sendBulk(
        array $users,
        string $type,
        array $data,
        array $channels = ['email'],
    ): array {
        $results = [
            'total' => count($users),
            'sent' => 0,
            'failed' => 0,
            'details' => [],
        ];

        foreach ($users as $user) {
            $result = $this->send($user, $type, $data, $channels);

            if (collect($result)->contains(fn ($r) => $r['success'] ?? false)) {
                $results['sent']++;
            } else {
                $results['failed']++;
            }

            $results['details'][$user->id] = $result;
        }

        return $results;
    }

    // ─── Queue Notification ────────────────────────────────────────────

    public function queue(
        User $user,
        string $type,
        array $data,
        array $channels = ['email'],
        ?string $queue = null,
    ): void {
        dispatch(function () use ($user, $type, $data, $channels) {
            $this->send($user, $type, $data, $channels);
        })->onQueue($queue ?? 'notifications');
    }
}
