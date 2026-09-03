<?php

namespace App\Modules\Payments\DTOs;

class PaymentResult
{
    public const STATUS_MANUAL = 'manual';
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_TIMEOUT = 'timeout';
    public const STATUS_REVERSED = 'reversed';
    public const STATUS_DUPLICATE = 'duplicate';
    public const STATUS_UNSUPPORTED = 'unsupported';

    public function __construct(
        public bool $success,
        public string $status,
        public string $providerName,
        public ?string $providerReference = null,
        public ?string $externalReference = null,
        public ?string $message = null,
        public array $metadata = [],
        public ?array $rawResponse = null,
    ) {
    }

    public static function make(
        bool $success,
        string $status,
        string $providerName,
        ?string $providerReference = null,
        ?string $externalReference = null,
        ?string $message = null,
        array $metadata = [],
        ?array $rawResponse = null,
    ): self {
        return new self($success, $status, $providerName, $providerReference, $externalReference, $message, $metadata, $rawResponse);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED || ! $this->success;
    }
}
