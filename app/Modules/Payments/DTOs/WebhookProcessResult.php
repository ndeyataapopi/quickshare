<?php

namespace App\Modules\Payments\DTOs;

use App\Modules\Payments\Models\PaymentWebhookEvent;

class WebhookProcessResult
{
    public function __construct(
        public bool $success,
        public string $status,
        public ?PaymentWebhookEvent $event = null,
        public ?string $message = null,
        public bool $duplicate = false,
    ) {
    }

    public static function processed(PaymentWebhookEvent $event, ?string $message = null): self
    {
        return new self(true, 'processed', $event, $message ?? 'Webhook processed.');
    }

    public static function duplicate(PaymentWebhookEvent $event, ?string $message = null): self
    {
        return new self(true, 'duplicate', $event, $message, true);
    }

    public static function invalid(?string $message = null): self
    {
        return new self(false, 'invalid', null, $message);
    }

    public static function failed(?string $message = null): self
    {
        return new self(false, 'failed', null, $message);
    }

    public static function ignored(?string $message = null): self
    {
        return new self(false, 'ignored', null, $message);
    }
}
