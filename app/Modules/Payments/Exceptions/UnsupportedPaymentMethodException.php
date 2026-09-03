<?php

namespace App\Modules\Payments\Exceptions;

use InvalidArgumentException;

class UnsupportedPaymentMethodException extends InvalidArgumentException
{
    public static function for(string $operation, string $paymentMethod, string $provider): self
    {
        return new self(
            "Provider [{$provider}] does not support operation [{$operation}] with method [{$paymentMethod}]."
        );
    }
}
