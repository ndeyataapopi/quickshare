<?php

namespace App\Modules\Payments\DTOs;

class OperationConfiguration
{
    public function __construct(
        public string $operation,
        public string $method,
        public string $mode,
        public string $provider,
    ) {
    }

    public static function make(
        string $operation,
        string $method,
        string $mode,
        string $provider,
    ): self {
        return new self($operation, $method, $mode, $provider);
    }

    public function isManual(): bool
    {
        return $this->mode === PaymentInstruction::EXECUTION_MANUAL
            || $this->method === PaymentInstruction::METHOD_MANUAL
            || $this->provider === 'manual';
    }

    public function isAutomated(): bool
    {
        return ! $this->isManual();
    }

    public function toArray(): array
    {
        return [
            'operation' => $this->operation,
            'method' => $this->method,
            'mode' => $this->mode,
            'provider' => $this->provider,
        ];
    }
}
