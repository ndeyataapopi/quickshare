<?php

namespace App\Modules\Payments\DTOs;

use InvalidArgumentException;

class PaymentInstruction
{
    public const OPERATION_LENDER_FUNDING = 'lender_funding';
    public const OPERATION_BORROWER_DISBURSEMENT = 'borrower_disbursement';
    public const OPERATION_BORROWER_REPAYMENT = 'borrower_repayment';
    public const OPERATION_LENDER_RETURN = 'lender_returns';

    public const EXECUTION_MANUAL = 'manual';
    public const EXECUTION_AUTOMATED = 'automated';

    public const METHOD_MANUAL = 'manual';
    public const METHOD_PAYMENT_LINK = 'payment_link';
    public const METHOD_DEBIT_ORDER = 'debit_order';
    public const METHOD_BANK_PAYOUT = 'bank_payout';
    public const METHOD_WALLET_PAYOUT = 'wallet_payout';

    public static function operations(): array
    {
        return [
            self::OPERATION_LENDER_FUNDING,
            self::OPERATION_BORROWER_DISBURSEMENT,
            self::OPERATION_BORROWER_REPAYMENT,
            self::OPERATION_LENDER_RETURN,
        ];
    }

    public static function methods(): array
    {
        return [
            self::METHOD_MANUAL,
            self::METHOD_PAYMENT_LINK,
            self::METHOD_DEBIT_ORDER,
            self::METHOD_BANK_PAYOUT,
            self::METHOD_WALLET_PAYOUT,
        ];
    }

    public static function executionModes(): array
    {
        return [self::EXECUTION_MANUAL, self::EXECUTION_AUTOMATED];
    }

    public function __construct(
        public string $operation,
        public string $paymentMethod,
        public string $executionMode,
        public float $amount,
        public string $reference,
        public string $currency = 'NAD',
        public ?array $sourceAccount = null,
        public ?array $destinationAccount = null,
        public ?string $description = null,
        public ?int $loanId = null,
        public ?int $userId = null,
        public ?string $provider = null,
        public array $metadata = [],
    ) {
        if (! in_array($operation, self::operations(), true)) {
            throw new InvalidArgumentException("Unsupported payment operation: {$operation}");
        }

        if (! in_array($paymentMethod, self::methods(), true)) {
            throw new InvalidArgumentException("Unsupported payment method: {$paymentMethod}");
        }

        if (! in_array($executionMode, self::executionModes(), true)) {
            throw new InvalidArgumentException("Unsupported execution mode: {$executionMode}");
        }
    }

    public function isManual(): bool
    {
        return $this->executionMode === self::EXECUTION_MANUAL
            || $this->paymentMethod === self::METHOD_MANUAL;
    }

    public function isAutomated(): bool
    {
        return ! $this->isManual();
    }
}
