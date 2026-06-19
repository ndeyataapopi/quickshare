<?php

namespace App\Modules\Notifications\Channels;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsChannel
{
    // ─── Configuration ─────────────────────────────────────────────────

    protected string $provider;
    protected array $config;

    public function __construct()
    {
        $this->provider = config('notifications.sms.provider', 'twilio');
        $this->config = config("notifications.sms.{$this->provider}", []);
    }

    // ─── Send SMS ──────────────────────────────────────────────────────

    public function send(User $user, string $type, array $data): array
    {
        $message = $this->formatMessage($type, $data);
        $phone = $this->formatPhone($user->phone);

        return match ($this->provider) {
            'twilio' => $this->sendViaTwilio($phone, $message),
            'aws_sns' => $this->sendViaAwsSns($phone, $message),
            'africas_talking' => $this->sendViaAfricaStalking($phone, $message),
            default => $this->sendViaLog($phone, $message), // For testing
        };
    }

    // ─── Format Message ──────────────────────────────────────────────

    protected function formatMessage(string $type, array $data): string
    {
        $templates = [
            'kyc_approved' => 'QuickShare: Your KYC has been approved! You can now apply for loans.',
            'kyc_rejected' => 'QuickShare: Your KYC was rejected. Reason: ' . ($data['reason'] ?? 'Please contact support.'),
            'loan_funded' => 'QuickShare: Your loan ' . ($data['reference'] ?? '') . ' is now fully funded and awaiting disbursement.',
            'loan_disbursed' => 'QuickShare: Your loan ' . ($data['reference'] ?? '') . ' has been disbursed. Amount: ' . config('loans.currency_symbol', 'N$') . ($data['amount'] ?? '0'),
            'repayment_reminder' => 'QuickShare Reminder: Your repayment of ' . config('loans.currency_symbol', 'N$') . ($data['amount'] ?? '0') . ' is due on ' . ($data['due_date'] ?? 'soon') . '.',
            'repayment_overdue' => 'QuickShare URGENT: Your repayment of ' . config('loans.currency_symbol', 'N$') . ($data['amount'] ?? '0') . ' is now overdue. Please pay immediately.',
            'repayment_received' => 'QuickShare: Thank you! Your repayment of ' . config('loans.currency_symbol', 'N$') . ($data['amount'] ?? '0') . ' has been received.',
            'welcome' => 'Welcome to QuickShare! Your account is ready. Download our app to get started.',
            'password_reset' => 'QuickShare: Your password reset code is: ' . ($data['code'] ?? '000000'),
        ];

        return $templates[$type] ?? 'QuickShare: You have a new notification.';
    }

    // ─── Format Phone ────────────────────────────────────────────────────

    protected function formatPhone(?string $phone): string
    {
        if (! $phone) {
            return '';
        }

        // Remove spaces and ensure starts with +
        $phone = preg_replace('/\s+/', '', $phone);
        
        if (! str_starts_with($phone, '+')) {
            // Assume South African number if no country code
            if (str_starts_with($phone, '0')) {
                $phone = '+27' . substr($phone, 1);
            } else {
                $phone = '+' . $phone;
            }
        }

        return $phone;
    }

    // ─── Twilio Integration ────────────────────────────────────────────

    protected function sendViaTwilio(string $phone, string $message): array
    {
        $accountSid = $this->config['account_sid'] ?? null;
        $authToken = $this->config['auth_token'] ?? null;
        $fromNumber = $this->config['from_number'] ?? null;

        if (! $accountSid || ! $authToken) {
            // Log for testing if no credentials
            Log::info('[SMS-Twilio] Would send to ' . $phone . ': ' . $message);
            return [
                'success' => true,
                'channel' => 'sms',
                'provider' => 'twilio',
                'message_id' => 'test-' . uniqid(),
            ];
        }

        try {
            $response = Http::withBasicAuth($accountSid, $authToken)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                    'From' => $fromNumber,
                    'To' => $phone,
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'channel' => 'sms',
                    'provider' => 'twilio',
                    'message_id' => $response->json('sid'),
                ];
            }

            return [
                'success' => false,
                'channel' => 'sms',
                'error' => $response->body(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'channel' => 'sms',
                'error' => $e->getMessage(),
            ];
        }
    }

    // ─── AWS SNS Integration ─────────────────────────────────────────

    protected function sendViaAwsSns(string $phone, string $message): array
    {
        // Placeholder for AWS SNS integration
        Log::info('[SMS-AWS] Would send to ' . $phone . ': ' . $message);
        
        return [
            'success' => true,
            'channel' => 'sms',
            'provider' => 'aws_sns',
            'message_id' => 'aws-' . uniqid(),
        ];
    }

    // ─── Africa's Talking Integration ──────────────────────────────────

    protected function sendViaAfricaStalking(string $phone, string $message): array
    {
        // Placeholder for Africa's Talking integration
        Log::info('[SMS-AfricasTalking] Would send to ' . $phone . ': ' . $message);
        
        return [
            'success' => true,
            'channel' => 'sms',
            'provider' => 'africas_talking',
            'message_id' => 'at-' . uniqid(),
        ];
    }

    // ─── Log Channel (Testing) ───────────────────────────────────────

    protected function sendViaLog(string $phone, string $message): array
    {
        Log::info('[SMS-LOG] To: ' . $phone . ' | Message: ' . $message);
        
        return [
            'success' => true,
            'channel' => 'sms',
            'provider' => 'log',
            'message_id' => 'log-' . uniqid(),
        ];
    }
}
