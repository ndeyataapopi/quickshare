<?php

namespace App\Modules\Payments\Services;

use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\Loans\Models\DisbursementTransaction;
use App\Modules\Payments\Models\PaymentAuditLog;
use App\Modules\Repayments\Models\LenderRepayment;
use App\Modules\Repayments\Models\Repayment;
use Illuminate\Database\Eloquent\Model;

class PaymentReconciliationService
{
    /**
     * Compare a QuickShare expected amount with a provider-reported amount.
     *
     * Returns a structured result and writes an audit log. Amount mismatches
     * are flagged but never automatically modify financial allocations.
     */
    public function compareAmounts(
        Model $transaction,
        float $expectedAmount,
        float $reportedAmount,
        string $provider,
        ?string $providerReference = null,
        array $context = [],
    ): array {
        $matched = round($expectedAmount, 2) === round($reportedAmount, 2);
        $difference = round($reportedAmount - $expectedAmount, 2);
        $transactionType = $this->transactionType($transaction);

        $auditContext = array_merge($context, [
            'operation' => $context['operation'] ?? 'reconciliation',
            'provider' => $provider,
            'provider_reference' => $providerReference,
            'transaction_type' => $transactionType,
            'transaction_id' => $transaction->id,
            'transaction_reference' => $transaction->transaction_reference ?? null,
            'expected_amount' => $expectedAmount,
            'reported_amount' => $reportedAmount,
        ]);

        if ($matched) {
            PaymentAuditLog::log(
                operation: $context['operation'] ?? 'reconciliation',
                event: 'amount_reconciled',
                status: 'matched',
                message: "Provider amount [{$reportedAmount}] matches QuickShare expected amount [{$expectedAmount}].",
                context: $auditContext,
            );
        } else {
            PaymentAuditLog::log(
                operation: $context['operation'] ?? 'reconciliation',
                event: 'amount_mismatch',
                status: 'mismatch',
                message: "Provider amount [{$reportedAmount}] does not match expected amount [{$expectedAmount}]. Difference: {$difference}.",
                context: $auditContext,
            );
        }

        return [
            'matched' => $matched,
            'expected_amount' => $expectedAmount,
            'reported_amount' => $reportedAmount,
            'difference' => $difference,
            'provider' => $provider,
            'provider_reference' => $providerReference,
            'transaction_type' => $transactionType,
            'transaction_id' => $transaction->id,
        ];
    }

    /**
     * Record a settlement/reconciliation result from the provider without
     * applying it to financial allocations.
     */
    public function recordSettlement(
        Model $transaction,
        float $settlementAmount,
        string $provider,
        ?string $providerReference = null,
        array $context = [],
    ): array {
        $expectedAmount = $this->expectedAmount($transaction);
        $transactionType = $this->transactionType($transaction);

        $auditContext = array_merge($context, [
            'operation' => $context['operation'] ?? 'reconciliation',
            'provider' => $provider,
            'provider_reference' => $providerReference,
            'transaction_type' => $transactionType,
            'transaction_id' => $transaction->id,
            'transaction_reference' => $transaction->transaction_reference ?? null,
            'expected_amount' => $expectedAmount,
            'reported_amount' => $settlementAmount,
        ]);

        PaymentAuditLog::log(
            operation: $context['operation'] ?? 'reconciliation',
            event: 'settlement_recorded',
            status: 'recorded',
            message: "Provider settlement recorded for amount [{$settlementAmount}]. Expected [{$expectedAmount}]. No allocation changed.",
            context: $auditContext,
        );

        return [
            'matched' => round($expectedAmount, 2) === round($settlementAmount, 2),
            'expected_amount' => $expectedAmount,
            'settlement_amount' => $settlementAmount,
            'difference' => round($settlementAmount - $expectedAmount, 2),
            'provider' => $provider,
            'provider_reference' => $providerReference,
            'transaction_type' => $transactionType,
            'transaction_id' => $transaction->id,
        ];
    }

    protected function transactionType(Model $transaction): string
    {
        return match (get_class($transaction)) {
            FundingTransaction::class => 'funding_transaction',
            DisbursementTransaction::class => 'disbursement_transaction',
            Repayment::class => 'repayment',
            LenderRepayment::class => 'lender_repayment',
            default => 'unknown',
        };
    }

    protected function expectedAmount(Model $transaction): float
    {
        return (float) match (get_class($transaction)) {
            DisbursementTransaction::class => $transaction->gross_amount,
            FundingTransaction::class,
            Repayment::class,
            LenderRepayment::class => $transaction->amount,
            default => 0,
        };
    }
}
