<?php

namespace App\Modules\Payments\DTOs;

class WebhookResult
{
    public function __construct(
        public bool $success,
        public ?string $eventType = null,
        public ?string $reference = null,
        public ?string $providerReference = null,
        public ?string $providerEventId = null,
        public ?string $status = null,
        public ?float $amount = null,
        public ?string $currency = null,
        public array $metadata = [],
        public ?string $message = null,
    ) {
    }

    public static function handled(
        string $eventType,
        ?string $reference = null,
        ?string $providerReference = null,
        ?string $providerEventId = null,
        ?string $status = null,
        ?float $amount = null,
        ?string $currency = null,
        array $metadata = [],
        ?string $message = null,
    ): self {
        return new self(true, $eventType, $reference, $providerReference, $providerEventId, $status, $amount, $currency, $metadata, $message);
    }

    public static function notHandled(array $metadata = [], ?string $message = null): self
    {
        return new self(false, null, null, null, null, null, null, null, $metadata, $message);
    }
}
