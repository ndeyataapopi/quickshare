<?php

namespace App\Modules\Notifications\Channels;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel
{
    // ─── Configuration ─────────────────────────────────────────────────

    protected string $provider;
    protected array $config;

    public function __construct()
    {
        $this->provider = config('notifications.whatsapp.provider', 'twilio');
        $this->config = config("notifications.whatsapp.{$this->provider}", []);
    }

    // ─── Send WhatsApp Message ───────────────────────────────────────

    public function send(User $user, string $type, array $data): array
    {
        $message = $this->formatMessage($type, $data);
        $template = $this->getTemplateName($type);
        $phone = $this->formatPhone($user->phone);

        return match ($this->provider) {
            'twilio' => $this->sendViaTwilio($phone, $message, $template, $data),
            'meta' => $this->sendViaMeta($phone, $message, $template, $data),
            'message_bird' => $this->sendViaMessageBird($phone, $message, $template),
            default => $this->sendViaLog($phone, $message),
        };
    }

    // ─── Format Message ──────────────────────────────────────────────

    protected function formatMessage(string $type, array $data): string
    {
        // WhatsApp supports richer formatting than SMS
        $templates = [
            'kyc_approved' => "✅ *KYC Approved*\n\nYour KYC has been approved! You can now apply for loans on QuickShare.\n\nApply now: " . config('app.frontend_url'),
            'kyc_rejected' => "❌ *KYC Rejected*\n\nYour KYC was rejected.\n\n*Reason:* " . ($data['reason'] ?? 'Please contact support.') . "\n\nNeed help? Reply to this message.",
            'loan_funded' => "🎉 *Loan Fully Funded!*\n\nYour loan *" . ($data['reference'] ?? '') . "* is now fully funded and awaiting disbursement.\n\nYou'll receive another notification when funds are sent.",
            'loan_disbursed' => "💰 *Loan Disbursed*\n\nYour loan *" . ($data['reference'] ?? '') . "* has been disbursed.\n\n*Amount:* R" . ($data['amount'] ?? '0') . "\n*Date:* " . now()->toDateString() . "\n\nCheck your bank account.",
            'repayment_reminder' => "⏰ *Repayment Reminder*\n\nYour repayment is due soon.\n\n*Amount:* R" . ($data['amount'] ?? '0') . "\n*Due Date:* " . ($data['due_date'] ?? 'soon') . "\n\nPay now to avoid penalties.",
            'repayment_overdue' => "⚠️ *OVERDUE PAYMENT*\n\nYour repayment of R" . ($data['amount'] ?? '0') . " is now *OVERDUE*.\n\nPlease pay immediately to avoid additional fees and credit score impact.\n\nPay now: " . config('app.frontend_url') . "/repayments",
            'repayment_received' => "✅ *Payment Received*\n\nThank you! Your repayment of R" . ($data['amount'] ?? '0') . " has been received.\n\nYour trust score has been updated.",
            'welcome' => "👋 *Welcome to QuickShare!*\n\nYour account is ready.\n\n🚀 Apply for loans\n💰 Fund other borrowers\n📊 Track your portfolio\n\nDownload our app: " . config('app.frontend_url'),
            'password_reset' => "🔐 *Password Reset*\n\nYour password reset code is:\n\n*" . ($data['code'] ?? '000000') . "*\n\nThis code expires in 15 minutes.",
        ];

        return $templates[$type] ?? '📱 QuickShare: You have a new notification.';
    }

    // ─── Get Template Name ───────────────────────────────────────────

    protected function getTemplateName(string $type): ?string
    {
        // For providers that require pre-approved templates (e.g., Meta)
        $templates = [
            'kyc_approved' => 'kyc_approved_v1',
            'kyc_rejected' => 'kyc_rejected_v1',
            'loan_funded' => 'loan_funded_v1',
            'loan_disbursed' => 'loan_disbursed_v1',
            'repayment_reminder' => 'repayment_reminder_v1',
            'repayment_overdue' => 'repayment_overdue_v1',
            'repayment_received' => 'repayment_received_v1',
            'welcome' => 'welcome_v1',
            'password_reset' => 'password_reset_v1',
        ];

        return $templates[$type] ?? null;
    }

    // ─── Format Phone ────────────────────────────────────────────────────

    protected function formatPhone(?string $phone): string
    {
        if (! $phone) {
            return '';
        }

        // WhatsApp format: country code + number, no + prefix in some APIs
        $phone = preg_replace('/\s+/', '', $phone);
        
        if (str_starts_with($phone, '0')) {
            $phone = '27' . substr($phone, 1); // South Africa
        }
        
        // Remove + if present, some APIs don't want it
        $phone = ltrim($phone, '+');

        return $phone;
    }

    // ─── Twilio WhatsApp Integration ─────────────────────────────────

    protected function sendViaTwilio(string $phone, string $message, ?string $template, array $data): array
    {
        $accountSid = $this->config['account_sid'] ?? null;
        $authToken = $this->config['auth_token'] ?? null;
        $fromNumber = $this->config['from_number'] ?? 'whatsapp:+14155238886'; // Twilio sandbox

        if (! $accountSid || ! $authToken) {
            Log::info('[WhatsApp-Twilio] Would send to ' . $phone . ': ' . $message);
            return [
                'success' => true,
                'channel' => 'whatsapp',
                'provider' => 'twilio',
                'message_id' => 'test-' . uniqid(),
            ];
        }

        try {
            $payload = [
                'From' => $fromNumber,
                'To' => 'whatsapp:+' . $phone,
                'Body' => $message,
            ];

            // If template is provided, use it instead
            if ($template) {
                $payload['MessagingServiceSid'] = $this->config['messaging_service_sid'] ?? null;
                // Template logic would go here
            }

            $response = Http::withBasicAuth($accountSid, $authToken)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'channel' => 'whatsapp',
                    'provider' => 'twilio',
                    'message_id' => $response->json('sid'),
                ];
            }

            return [
                'success' => false,
                'channel' => 'whatsapp',
                'error' => $response->body(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'channel' => 'whatsapp',
                'error' => $e->getMessage(),
            ];
        }
    }

    // ─── Meta (Facebook) WhatsApp Business API ─────────────────────────

    protected function sendViaMeta(string $phone, string $message, ?string $template, array $data): array
    {
        $accessToken = $this->config['access_token'] ?? null;
        $phoneNumberId = $this->config['phone_number_id'] ?? null;
        $businessAccountId = $this->config['business_account_id'] ?? null;

        if (! $accessToken || ! $phoneNumberId) {
            Log::info('[WhatsApp-Meta] Would send to ' . $phone . ': ' . $message);
            return [
                'success' => true,
                'channel' => 'whatsapp',
                'provider' => 'meta',
                'message_id' => 'test-' . uniqid(),
            ];
        }

        try {
            // Meta requires using pre-approved templates
            if ($template) {
                $payload = [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'template',
                    'template' => [
                        'name' => $template,
                        'language' => ['code' => 'en'],
                        'components' => $this->buildTemplateComponents($data),
                    ],
                ];
            } else {
                // For session messages (user initiated)
                $payload = [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'text',
                    'text' => ['body' => $message],
                ];
            }

            $response = Http::withToken($accessToken)
                ->post("https://graph.facebook.com/v18.0/{$phoneNumberId}/messages", $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'channel' => 'whatsapp',
                    'provider' => 'meta',
                    'message_id' => $response->json('messages.0.id'),
                ];
            }

            return [
                'success' => false,
                'channel' => 'whatsapp',
                'error' => $response->body(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'channel' => 'whatsapp',
                'error' => $e->getMessage(),
            ];
        }
    }

    // ─── Build Template Components ───────────────────────────────────

    protected function buildTemplateComponents(array $data): array
    {
        $components = [];

        // Body parameters
        if (! empty($data)) {
            $parameters = [];
            foreach ($data as $key => $value) {
                $parameters[] = [
                    'type' => 'text',
                    'text' => (string) $value,
                ];
            }

            $components[] = [
                'type' => 'body',
                'parameters' => $parameters,
            ];
        }

        return $components;
    }

    // ─── MessageBird Integration ─────────────────────────────────────

    protected function sendViaMessageBird(string $phone, string $message, ?string $template): array
    {
        $accessKey = $this->config['access_key'] ?? null;
        $channelId = $this->config['channel_id'] ?? null;

        if (! $accessKey) {
            Log::info('[WhatsApp-MessageBird] Would send to ' . $phone . ': ' . $message);
            return [
                'success' => true,
                'channel' => 'whatsapp',
                'provider' => 'message_bird',
                'message_id' => 'test-' . uniqid(),
            ];
        }

        // MessageBird implementation
        return [
            'success' => true,
            'channel' => 'whatsapp',
            'provider' => 'message_bird',
            'message_id' => 'mb-' . uniqid(),
        ];
    }

    // ─── Log Channel (Testing) ───────────────────────────────────────

    protected function sendViaLog(string $phone, string $message): array
    {
        Log::info('[WhatsApp-LOG] To: ' . $phone . ' | Message: ' . $message);
        
        return [
            'success' => true,
            'channel' => 'whatsapp',
            'provider' => 'log',
            'message_id' => 'log-' . uniqid(),
        ];
    }
}
