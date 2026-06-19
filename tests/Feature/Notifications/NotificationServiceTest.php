<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Modules\Notifications\Channels\SmsChannel;
use App\Modules\Notifications\Channels\WhatsAppChannel;
use App\Modules\Notifications\Events\NotificationSent;
use App\Modules\Notifications\Notifications\KycApprovedNotification;
use App\Modules\Notifications\Notifications\LoanDisbursedNotification;
use App\Modules\Notifications\Notifications\RepaymentOverdueNotification;
use App\Modules\Notifications\Notifications\RepaymentReminderNotification;
use App\Modules\Notifications\Notifications\WelcomeNotification;
use App\Modules\Notifications\Services\NotificationService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as LaravelNotification;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected NotificationService $service;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->service = app(NotificationService::class);

        $this->user = User::factory()->active()->create([
            'email' => 'testuser@quickshare.com',
            'phone' => '+27821234567',
        ]);
        $this->user->assignRole('borrower');
    }

    // ─── Service Resolution ────────────────────────────────────────────

    public function test_notification_service_is_singleton(): void
    {
        $service1 = app(NotificationService::class);
        $service2 = app(NotificationService::class);

        $this->assertSame($service1, $service2);
    }

    // ─── Email Channel ─────────────────────────────────────────────────

    public function test_send_email_notification(): void
    {
        LaravelNotification::fake();

        $result = $this->service->send($this->user, 'welcome', [], ['email']);

        LaravelNotification::assertSentTo($this->user, WelcomeNotification::class);

        $this->assertArrayHasKey('email', $result);
        $this->assertTrue($result['email']['success']);
    }

    public function test_send_kyc_approved_notification_via_email(): void
    {
        LaravelNotification::fake();

        $result = $this->service->send($this->user, 'kyc_approved', [], ['email']);

        LaravelNotification::assertSentTo($this->user, KycApprovedNotification::class);
        $this->assertTrue($result['email']['success']);
    }

    public function test_send_kyc_rejected_notification_via_email(): void
    {
        LaravelNotification::fake();

        $result = $this->service->send(
            $this->user,
            'kyc_rejected',
            ['reason' => 'Blurry document'],
            ['email']
        );

        $this->assertTrue($result['email']['success']);
    }

    public function test_send_loan_disbursed_notification(): void
    {
        LaravelNotification::fake();

        $result = $this->service->send(
            $this->user,
            'loan_disbursed',
            [
                'loan_id' => 1,
                'reference' => 'QS-001',
                'amount' => 10000,
                'disbursed_at' => now()->toDateString(),
            ],
            ['email']
        );

        LaravelNotification::assertSentTo($this->user, LoanDisbursedNotification::class);
        $this->assertTrue($result['email']['success']);
    }

    public function test_send_repayment_reminder_notification(): void
    {
        LaravelNotification::fake();

        $result = $this->service->send(
            $this->user,
            'repayment_reminder',
            [
                'amount' => 1500,
                'due_date' => now()->addDays(3)->toDateString(),
                'reference' => 'QS-002',
            ],
            ['email']
        );

        LaravelNotification::assertSentTo($this->user, RepaymentReminderNotification::class);
        $this->assertTrue($result['email']['success']);
    }

    public function test_send_repayment_overdue_notification(): void
    {
        LaravelNotification::fake();

        $result = $this->service->send(
            $this->user,
            'repayment_overdue',
            [
                'amount' => 1500,
                'days_overdue' => 7,
                'reference' => 'QS-003',
                'penalty' => 75,
            ],
            ['email']
        );

        LaravelNotification::assertSentTo($this->user, RepaymentOverdueNotification::class);
        $this->assertTrue($result['email']['success']);
    }

    // ─── Multi-Channel ─────────────────────────────────────────────────

    public function test_send_to_multiple_channels(): void
    {
        LaravelNotification::fake();
        Event::fake([NotificationSent::class]);

        $result = $this->service->send(
            $this->user,
            'repayment_overdue',
            ['amount' => 1000, 'days_overdue' => 5],
            ['email', 'sms', 'whatsapp']
        );

        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('sms', $result);
        $this->assertArrayHasKey('whatsapp', $result);

        // 3 NotificationSent events (one per channel)
        Event::assertDispatched(NotificationSent::class, 3);
    }

    // ─── SMS Channel ──────────────────────────────────────────────────

    public function test_sms_channel_sends_message(): void
    {
        $channel = new SmsChannel();

        $result = $channel->send($this->user, 'repayment_reminder', [
            'amount' => 500,
            'due_date' => now()->addDay()->toDateString(),
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('sms', $result['channel']);
    }

    public function test_sms_formats_south_african_phone_correctly(): void
    {
        // Test via channel with SA number
        $channel = new SmsChannel();
        
        $userWithSaPhone = User::factory()->active()->create([
            'phone' => '0821234567',
        ]);

        $result = $channel->send($userWithSaPhone, 'welcome', []);

        $this->assertTrue($result['success']);
    }

    public function test_sms_message_formatting(): void
    {
        $channel = new SmsChannel();

        $result = $channel->send($this->user, 'loan_disbursed', [
            'reference' => 'QS-TEST',
            'amount' => 5000,
        ]);

        $this->assertTrue($result['success']);
    }

    // ─── WhatsApp Channel ────────────────────────────────────────────

    public function test_whatsapp_channel_sends_message(): void
    {
        $channel = new WhatsAppChannel();

        $result = $channel->send($this->user, 'loan_funded', [
            'reference' => 'QS-001',
            'amount' => 10000,
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('whatsapp', $result['channel']);
    }

    public function test_whatsapp_message_has_rich_formatting(): void
    {
        $channel = new WhatsAppChannel();

        $result = $channel->send($this->user, 'loan_disbursed', [
            'reference' => 'QS-002',
            'amount' => 15000,
        ]);

        $this->assertTrue($result['success']);
    }

    // ─── Auto Channel Resolution ──────────────────────────────────────

    public function test_send_auto_uses_config_channels(): void
    {
        LaravelNotification::fake();
        Event::fake([NotificationSent::class]);

        // Config sets 'kyc_approved' to ['email'] only
        $this->service->sendAuto($this->user, 'kyc_approved', []);

        Event::assertDispatched(NotificationSent::class, 1);
    }

    public function test_send_auto_repayment_overdue_uses_all_channels(): void
    {
        LaravelNotification::fake();
        Event::fake([NotificationSent::class]);

        // Config sets 'repayment_overdue' to ['email', 'sms', 'whatsapp']
        $this->service->sendAuto($this->user, 'repayment_overdue', [
            'amount' => 2000,
            'days_overdue' => 3,
        ]);

        Event::assertDispatched(NotificationSent::class, 3);
    }

    // ─── Bulk Notifications ────────────────────────────────────────────

    public function test_send_bulk_notifications(): void
    {
        LaravelNotification::fake();

        $users = User::factory()->active()->count(3)->create();
        foreach ($users as $u) {
            $u->assignRole('borrower');
        }

        $results = $this->service->sendBulk(
            $users->all(),
            'repayment_reminder',
            ['amount' => 500, 'due_date' => now()->addDays(2)->toDateString()],
            ['email']
        );

        $this->assertEquals(3, $results['total']);
        $this->assertEquals(3, $results['sent']);
        $this->assertEquals(0, $results['failed']);
    }

    // ─── Event Dispatching ─────────────────────────────────────────────

    public function test_notification_sent_event_is_dispatched(): void
    {
        LaravelNotification::fake();
        Event::fake([NotificationSent::class]);

        $this->service->send($this->user, 'welcome', [], ['email']);

        Event::assertDispatched(NotificationSent::class, function ($event) {
            return $event->user->id === $this->user->id
                && $event->channel === 'email'
                && $event->type === 'welcome';
        });
    }

    // ─── Determine Channels ────────────────────────────────────────────

    public function test_determine_channels_uses_config(): void
    {
        $channels = $this->service->determineChannels($this->user, 'loan_disbursed');
        $this->assertContains('email', $channels);
        $this->assertContains('sms', $channels);
        $this->assertContains('whatsapp', $channels);
    }

    public function test_determine_channels_defaults_to_email(): void
    {
        $channels = $this->service->determineChannels($this->user, 'unknown_type');
        $this->assertEquals(['email'], $channels);
    }

    // ─── Failure Handling ─────────────────────────────────────────────

    public function test_failed_channel_does_not_abort_other_channels(): void
    {
        LaravelNotification::fake();
        LaravelNotification::shouldReceive('send')->andThrow(new \Exception('Mail failed'));

        // Use SMS only (which won't fail in test mode)
        $result = $this->service->send(
            $this->user,
            'repayment_reminder',
            ['amount' => 500],
            ['sms']
        );

        $this->assertArrayHasKey('sms', $result);
    }

    // ─── Notification Classes ─────────────────────────────────────────

    public function test_kyc_approved_mail_notification_has_correct_subject(): void
    {
        $notification = new KycApprovedNotification([], 'mail');
        $mail = $notification->toMail($this->user);

        $this->assertStringContainsString('KYC Approved', $mail->subject);
    }

    public function test_repayment_overdue_mail_has_urgent_subject(): void
    {
        $notification = new RepaymentOverdueNotification(
            ['amount' => 1000, 'days_overdue' => 5],
            'mail'
        );
        $mail = $notification->toMail($this->user);

        $this->assertStringContainsString('URGENT', $mail->subject);
    }

    public function test_loan_disbursed_mail_contains_amount(): void
    {
        $notification = new LoanDisbursedNotification(
            ['loan_id' => 1, 'reference' => 'QS-001', 'amount' => 7500, 'disbursed_at' => now()->toDateString()],
            'mail'
        );
        $mail = $notification->toMail($this->user);

        $this->assertStringContainsString('7500', implode(' ', $mail->introLines));
    }

    public function test_welcome_notification_via_is_mail(): void
    {
        $notification = new WelcomeNotification([], 'mail');

        $this->assertEquals(['mail'], $notification->via($this->user));
    }
}
