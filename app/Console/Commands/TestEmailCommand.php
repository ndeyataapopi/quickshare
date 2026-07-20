<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class TestEmailCommand extends Command
{
    protected $signature = 'email:test {recipient?} {--queue : Dispatch the test email through the queue instead of sending it synchronously}';
    protected $description = 'Send a system test email to verify the mail pipeline.';

    public function handle(): int
    {
        $to = $this->argument('recipient') ?: config('mail.from.address');

        $appName = config('app.name');
        $environment = config('app.env');
        $mailDriver = config('mail.default');
        $timestamp = now()->toDateTimeString();
        $hostname = gethostname() ?: 'unknown';
        $fromName = config('mail.from.name');
        $fromAddress = config('mail.from.address');

        $body = <<<TEXT
System Email Test
=================

Application Name: {$appName}
Environment:      {$environment}
Timestamp:        {$timestamp}
Mail Driver:      {$mailDriver}
Server Hostname:  {$hostname}
Recipient:        {$to}
From Address:     "{$fromName}" <{$fromAddress}>

If you received this email, the Laravel mail pipeline is configured correctly.
TEXT;

        $this->info("Sending test email to {$to} using driver: {$mailDriver}");

        if ($this->option('queue')) {
            dispatch(function () use ($to, $body) {
                Mail::raw($body, function ($message) use ($to) {
                    $message->to($to)
                        ->subject('System Email Test');
                });
            })->onQueue(config('notifications.queue.default', 'notifications'));

            $this->info('Test email job dispatched to the notifications queue.');
            $this->info('Run `php artisan queue:work --queue=notifications --stop-when-empty` to process it.');

            return Command::SUCCESS;
        }

        try {
            Mail::raw($body, function ($message) use ($to) {
                $message->to($to)
                    ->subject('System Email Test');
            });

            $this->info('Mail accepted by Laravel.');
            $this->info('Check storage/logs/laravel.log (for log driver) or your SMTP service for actual delivery.');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to send test email.');
            $this->error($e->getMessage());

            Log::error('Test email failed', [
                'recipient' => $to,
                'driver' => $mailDriver,
                'error' => $e->getMessage(),
            ]);

            return Command::FAILURE;
        }
    }
}
