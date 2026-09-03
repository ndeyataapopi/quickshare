<?php

namespace App\Modules\Payments\Services;

use App\Modules\Payments\DTOs\PaymentInstruction;
use App\Modules\Payments\DTOs\PaymentResult;
use App\Modules\Payments\Providers\PaymentProviderManager;

class PaymentExecutionService
{
    public function __construct(
        protected PaymentProviderManager $manager,
    ) {
    }

    public function execute(PaymentInstruction $instruction): PaymentResult
    {
        $provider = $this->resolveProvider($instruction);

        return match ($instruction->operation) {
            PaymentInstruction::OPERATION_LENDER_FUNDING => $provider->initiateFunding($instruction),
            PaymentInstruction::OPERATION_BORROWER_DISBURSEMENT => $provider->initiateDisbursement($instruction),
            PaymentInstruction::OPERATION_BORROWER_REPAYMENT => $provider->initiateRepayment($instruction),
            PaymentInstruction::OPERATION_LENDER_RETURN => $provider->initiateLenderReturn($instruction),
        };
    }

    protected function resolveProvider(PaymentInstruction $instruction): \App\Modules\Payments\Contracts\PaymentProviderInterface
    {
        if ($instruction->isManual()) {
            return $this->manager->resolve('manual');
        }

        $providerName = $instruction->provider ?? config('payment_providers.default_provider', 'manual');

        if ($providerName === 'manual' || ! $providerName) {
            throw new \InvalidArgumentException(
                "Automated operation [{$instruction->operation}] requires a non-manual provider."
            );
        }

        $provider = $this->manager->resolve($providerName);

        if (! $provider->supports($instruction->operation, $instruction->paymentMethod)) {
            throw new \InvalidArgumentException(
                "Provider [{$providerName}] does not support operation [{$instruction->operation}] with method [{$instruction->paymentMethod}]."
            );
        }

        return $provider;
    }

    public function executeFunding(PaymentInstruction $instruction): PaymentResult
    {
        return $this->execute($instruction);
    }

    public function executeDisbursement(PaymentInstruction $instruction): PaymentResult
    {
        return $this->execute($instruction);
    }

    public function executeRepayment(PaymentInstruction $instruction): PaymentResult
    {
        return $this->execute($instruction);
    }

    public function executeLenderReturn(PaymentInstruction $instruction): PaymentResult
    {
        return $this->execute($instruction);
    }
}
