<?php

namespace App\Modules\Payments\Exceptions;

use InvalidArgumentException;

class InvalidPaymentConfigurationException extends InvalidArgumentException
{
    public static function unknownOperation(string $operation): self
    {
        return new self("Unknown payment operation: {$operation}.");
    }

    public static function unknownMethod(string $operation, string $method): self
    {
        return new self("Unknown payment method [{$method}] for operation [{$operation}].");
    }

    public static function unknownMode(string $operation, string $mode): self
    {
        return new self("Unknown execution mode [{$mode}] for operation [{$operation}].");
    }

    public static function automatedRequiresProvider(string $operation): self
    {
        return new self("Operation [{$operation}] is automated but no provider is configured.");
    }

    public static function manualRequiresManualProvider(string $operation): self
    {
        return new self("Operation [{$operation}] is manual but provider is not manual.");
    }

    public static function providerNotConfigured(string $operation, string $provider): self
    {
        return new self("Provider [{$provider}] for operation [{$operation}] is not registered or not configured.");
    }

    public static function providerDoesNotSupport(string $operation, string $method, string $provider): self
    {
        return new self("Provider [{$provider}] does not support method [{$method}] for operation [{$operation}].");
    }
}
