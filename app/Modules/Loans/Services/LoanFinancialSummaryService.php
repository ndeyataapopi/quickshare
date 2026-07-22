<?php

namespace App\Modules\Loans\Services;

use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\Funding\Models\Investment;
use App\Modules\Loans\Models\DisbursementTransaction;
use App\Modules\Loans\Models\Loan;
use App\Modules\Repayments\Models\LenderRepayment;
use App\Modules\Repayments\Models\Repayment;
use Illuminate\Support\Collection;

class LoanFinancialSummaryService
{
    public function __construct(protected LoanService $loanService)
    {
    }

    public function generate(Loan $loan): array
    {
        $loan->load([
            'borrower:id,first_name,last_name,email',
            'fundingTransactions.lender:id,first_name,last_name,email',
            'investments.lender:id,first_name,last_name,email',
            'disbursements',
            'repayments.lenderRepayments.lender:id,first_name,last_name,email',
        ]);

        $loanInfo = $this->buildLoanInfo($loan);
        $fundingSummary = $this->buildFundingSummary($loan);
        $incomingFunds = $this->buildIncomingFunds($loan);
        $disbursement = $this->buildBorrowerDisbursement($loan);
        $repaymentSummary = $this->buildRepaymentSummary($loan);
        $lenderDistribution = $this->buildLenderDistribution($loan);
        $platformSummary = $this->buildPlatformSummary($loan, $repaymentSummary);
        $reconciliation = $this->buildReconciliation(
            $loan,
            $fundingSummary,
            $disbursement,
            $repaymentSummary,
            $lenderDistribution,
            $platformSummary,
        );

        return [
            'loan_info' => $loanInfo,
            'funding_summary' => $fundingSummary,
            'incoming_funds' => $incomingFunds,
            'borrower_disbursement' => $disbursement,
            'repayment_summary' => $repaymentSummary,
            'lender_distribution' => $lenderDistribution,
            'platform_summary' => $platformSummary,
            'reconciliation' => $reconciliation,
        ];
    }

    protected function buildLoanInfo(Loan $loan): array
    {
        return [
            'loan_id' => $loan->id,
            'reference' => $loan->reference,
            'borrower_name' => $loan->borrower?->first_name . ' ' . $loan->borrower?->last_name,
            'borrower_email' => $loan->borrower?->email,
            'requested_amount' => round((float) $loan->requested_amount, 2),
            'approved_amount' => round($this->loanService->loanPrincipal($loan), 2),
            'platform_fee' => round((float) $loan->platform_fee, 2),
            'lender_return' => round($this->loanService->loanLenderReturnAmount($loan), 2),
            'total_repayment' => round((float) $loan->total_repayment, 2),
            'interest_rate' => (float) $loan->interest_rate,
            'loan_term_days' => (int) $loan->loan_term_days,
            'status' => $loan->status,
            'funded_amount' => round((float) $loan->funded_amount, 2),
            'disbursed_at' => $loan->disbursed_at?->toDateString(),
            'completed_at' => $loan->completed_at?->toDateString(),
        ];
    }

    protected function buildFundingSummary(Loan $loan): array
    {
        $confirmed = $loan->fundingTransactions->where('status', 'confirmed');
        $pending = $loan->fundingTransactions->where('status', 'pending');
        $rejected = $loan->fundingTransactions->whereIn('status', ['rejected', 'cancelled']);

        $targetAmount = $this->loanService->loanPrincipal($loan);
        $totalFundingReceived = round($confirmed->sum('amount'), 2);
        $totalFundingRequested = round($targetAmount, 2);

        $contributions = $confirmed->map(function (FundingTransaction $ft) use ($loan) {
            return [
                'lender_id' => $ft->lender_id,
                'lender_name' => $ft->lender?->first_name . ' ' . $ft->lender?->last_name,
                'amount' => round((float) $ft->amount, 2),
                'interest_rate' => (float) $ft->interest_rate,
                'expected_return' => round((float) $ft->expected_return, 2),
                'funding_percentage' => $this->loanService->fundingPercentage($loan, (float) $ft->amount),
                'confirmed_at' => $ft->confirmed_at?->toDateTimeString(),
                'transaction_reference' => $ft->transaction_reference,
                'status' => $ft->status,
            ];
        })->values();

        return [
            'investor_count' => $confirmed->unique('lender_id')->count(),
            'target_amount' => $totalFundingRequested,
            'total_received' => $totalFundingReceived,
            'total_pending' => round($pending->sum('amount'), 2),
            'total_rejected' => round($rejected->sum('amount'), 2),
            'remaining' => $this->loanService->remainingFunding($loan),
            'progress_percent' => $this->loanService->fundingProgress($loan),
            'contributions' => $contributions,
            'funding_dates' => $confirmed->pluck('confirmed_at')
                ->filter()
                ->map(fn ($d) => $d->toDateString())
                ->unique()
                ->sort()
                ->values()
                ->all(),
        ];
    }

    protected function buildIncomingFunds(Loan $loan): array
    {
        $transactions = $loan->fundingTransactions()
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('created_at')
            ->get();

        return $transactions->map(function (FundingTransaction $ft) {
            return [
                'type' => 'funding',
                'lender_name' => $ft->lender?->first_name . ' ' . $ft->lender?->last_name,
                'amount' => round((float) $ft->amount, 2),
                'payment_date' => $ft->payment_date?->toDateTimeString(),
                'transaction_reference' => $ft->transaction_reference,
                'payment_reference' => $ft->payment_reference,
                'status' => $ft->status,
                'confirmed_at' => $ft->confirmed_at?->toDateTimeString(),
            ];
        })->values()->all();
    }

    protected function buildBorrowerDisbursement(Loan $loan): array
    {
        $outgoing = $loan->disbursements()
            ->where('direction', 'outgoing')
            ->orderBy('created_at')
            ->get();

        return $outgoing->map(function (DisbursementTransaction $dt) {
            return [
                'gross_amount' => round((float) $dt->gross_amount, 2),
                'platform_fee' => round((float) $dt->platform_fee, 2),
                'net_amount' => round((float) $dt->net_amount, 2),
                'date' => $dt->processed_at?->toDateTimeString(),
                'payment_method' => $dt->payment_method,
                'transaction_reference' => $dt->transaction_reference,
                'external_reference' => $dt->external_reference,
                'status' => $dt->status,
                'borrower_confirmed_at' => $dt->borrower_confirmed_at?->toDateTimeString(),
            ];
        })->values()->all();
    }

    protected function buildRepaymentSummary(Loan $loan): array
    {
        $repayments = $loan->repayments()->orderBy('due_date')->get();

        $scheduledTotal = round($repayments->sum('amount'), 2);
        $actualRepaid = round($repayments->where('status', 'paid')->sum('amount'), 2);
        $totalPenalties = round($repayments->sum('penalty'), 2);
        $paidPenalties = round($repayments->where('status', 'paid')->sum('penalty'), 2);

        $alreadyPaid = $repayments->where('status', 'paid')->sum(fn ($r) => (float) $r->amount + (float) $r->penalty);
        $outstandingBalance = $this->loanService->outstandingBalance(
            (float) $loan->total_repayment,
            (float) $alreadyPaid,
            (float) $totalPenalties,
        );

        $repaymentDetails = $repayments->map(function (Repayment $r) {
            return [
                'id' => $r->id,
                'amount' => round((float) $r->amount, 2),
                'principal' => round((float) $r->principal, 2),
                'interest' => round((float) $r->interest, 2),
                'platform_fee' => round((float) $r->platform_fee, 2),
                'penalty' => round((float) $r->penalty, 2),
                'due_date' => $r->due_date?->toDateString(),
                'paid_date' => $r->paid_date?->toDateString(),
                'status' => $r->status,
                'transaction_reference' => $r->transaction_reference,
            ];
        })->values();

        return [
            'scheduled_total' => $scheduledTotal,
            'actual_repaid' => $actualRepaid,
            'outstanding_balance' => $outstandingBalance,
            'total_penalties' => $totalPenalties,
            'paid_penalties' => $paidPenalties,
            'repayment_count' => $repayments->count(),
            'paid_count' => $repayments->where('status', 'paid')->count(),
            'repayments' => $repaymentDetails,
        ];
    }

    protected function buildLenderDistribution(Loan $loan): array
    {
        $investments = $loan->investments()
            ->with('lender:id,first_name,last_name,email')
            ->get();

        $lenderRepayments = LenderRepayment::whereHas('repayment', fn ($q) => $q->where('loan_id', $loan->id))
            ->with('lender:id,first_name,last_name,email')
            ->get();

        return $investments->map(function (Investment $inv) use ($lenderRepayments) {
            $lrForLender = $lenderRepayments->where('lender_id', $inv->lender_id)
                ->where('funding_transaction_id', $inv->funding_transaction_id);

            return [
                'lender_id' => $inv->lender_id,
                'lender_name' => $inv->lender?->first_name . ' ' . $inv->lender?->last_name,
                'invested_amount' => round((float) $inv->amount, 2),
                'expected_return' => round((float) $inv->expected_return, 2),
                'actual_return' => round((float) $inv->actual_return, 2),
                'principal_returned' => round($lrForLender->sum('principal_return'), 2),
                'interest_earned' => round($lrForLender->sum('interest_earned'), 2),
                'total_paid' => round($lrForLender->sum('amount'), 2),
                'investment_status' => $inv->status,
                'funded_at' => $inv->funded_at?->toDateTimeString(),
            ];
        })->values()->all();
    }

    protected function buildPlatformSummary(Loan $loan, array $repaymentSummary): array
    {
        $paidPlatformFees = round((float) $loan->repayments()
            ->where('status', 'paid')
            ->sum('platform_fee'), 2);
        $penalties = $repaymentSummary['paid_penalties'];

        return [
            'platform_fee_earned' => $paidPlatformFees,
            'processing_fees' => 0.00,
            'penalties_collected' => $penalties,
            'net_platform_revenue' => round($paidPlatformFees + $penalties, 2),
        ];
    }

    protected function buildReconciliation(
        Loan $loan,
        array $fundingSummary,
        array $disbursement,
        array $repaymentSummary,
        array $lenderDistribution,
        array $platformSummary,
    ): array {
        $moneyIn = round($fundingSummary['total_received'] + $repaymentSummary['actual_repaid'] + $repaymentSummary['paid_penalties'], 2);

        $disbursementOut = round(collect($disbursement)
            ->where('status', 'disbursed')
            ->sum('net_amount'), 2);

        $lenderRepaymentOut = round(collect($lenderDistribution)->sum('total_paid'), 2);

        $moneyOut = round($disbursementOut + $lenderRepaymentOut, 2);

        $platformRevenue = $platformSummary['net_platform_revenue'];

        $expectedMoneyOut = round($moneyOut + $platformRevenue, 2);

        $hasDisbursement = collect($disbursement)->where('status', 'disbursed')->count() > 0;

        $reconciled = ! $hasDisbursement || abs($moneyIn - $expectedMoneyOut) < 0.01;

        $checks = $this->runReconciliationChecks(
            $loan,
            $fundingSummary,
            $disbursement,
            $repaymentSummary,
            $lenderDistribution,
            $platformSummary,
        );

        $discrepancies = array_filter($checks, fn ($c) => ! $c['passed']);
        $allChecksPassed = $reconciled && count($discrepancies) === 0;

        return [
            'money_in' => $moneyIn,
            'money_out' => $moneyOut,
            'platform_revenue' => $platformRevenue,
            'money_out_plus_revenue' => $expectedMoneyOut,
            'reconciled' => $allChecksPassed,
            'equation' => 'Money In = Money Out + Platform Revenue',
            'checks' => array_values($checks),
            'discrepancies' => array_values($discrepancies),
        ];
    }

    protected function runReconciliationChecks(
        Loan $loan,
        array $fundingSummary,
        array $disbursement,
        array $repaymentSummary,
        array $lenderDistribution,
        array $platformSummary,
    ): array {
        $checks = [];

        $principal = $this->loanService->loanPrincipal($loan);
        $lenderReturn = $this->loanService->loanLenderReturnAmount($loan);
        $platformFee = (float) $loan->platform_fee;
        $totalRepayment = (float) $loan->total_repayment;

        $checks[] = $this->check(
            'funding_totals',
            'Funding totals reconcile',
            abs($fundingSummary['total_received'] - (float) $loan->funded_amount) < 0.01,
            'Funding received: ' . $fundingSummary['total_received'] . ', Loan funded_amount: ' . (float) $loan->funded_amount,
        );

        $investmentTotal = collect($loan->investments)->sum(fn ($inv) => (float) $inv->amount);
        $checks[] = $this->check(
            'investment_totals',
            'Investment totals reconcile with funding',
            abs($fundingSummary['total_received'] - $investmentTotal) < 0.01,
            'Funding received: ' . $fundingSummary['total_received'] . ', Investments total: ' . round($investmentTotal, 2),
        );

        $scheduledTotal = $repaymentSummary['scheduled_total'];
        $hasSchedule = $repaymentSummary['repayment_count'] > 0;
        $checks[] = $this->check(
            'repayment_totals',
            'Repayment scheduled total matches loan total_repayment',
            ! $hasSchedule || abs($scheduledTotal - $totalRepayment) < 0.01,
            $hasSchedule ? 'Scheduled: ' . $scheduledTotal . ', Loan total_repayment: ' . $totalRepayment : 'No repayment schedule created yet',
        );

        $disbursedNet = collect($disbursement)
            ->where('status', 'disbursed')
            ->sum('net_amount');
        $hasDisbursement = collect($disbursement)->where('status', 'disbursed')->count() > 0;
        $checks[] = $this->check(
            'disbursement_totals',
            'Disbursement net amount matches principal',
            ! $hasDisbursement || abs($disbursedNet - $principal) < 0.01,
            $hasDisbursement ? 'Disbursed net: ' . round($disbursedNet, 2) . ', Principal: ' . $principal : 'No disbursement processed yet',
        );

        $lenderTotalPaid = collect($lenderDistribution)->sum('total_paid');
        $lenderPrincipalReturned = collect($lenderDistribution)->sum('principal_returned');
        $lenderInterestEarned = collect($lenderDistribution)->sum('interest_earned');

        $checks[] = $this->check(
            'lender_allocations',
            'Lender allocations: principal + interest = total paid to lenders',
            abs($lenderPrincipalReturned + $lenderInterestEarned - $lenderTotalPaid) < 0.01,
            'Principal returned: ' . round($lenderPrincipalReturned, 2) . ', Interest earned: ' . round($lenderInterestEarned, 2) . ', Total paid: ' . round($lenderTotalPaid, 2),
        );

        if ($loan->isCompleted()) {
            $checks[] = $this->check(
                'lender_full_return',
                'Lenders received full expected return (completed loan)',
                abs($lenderTotalPaid - ($principal + $lenderReturn)) < 0.01,
                'Lender total paid: ' . round($lenderTotalPaid, 2) . ', Expected (principal + lender_return): ' . round($principal + $lenderReturn, 2),
            );

            $checks[] = $this->check(
                'repayment_full',
                'Borrower repaid full total_repayment (completed loan)',
                abs($repaymentSummary['actual_repaid'] - $totalRepayment) < 0.01,
                'Actual repaid: ' . $repaymentSummary['actual_repaid'] . ', Total repayment: ' . $totalRepayment,
            );

            $checks[] = $this->check(
                'outstanding_zero',
                'Outstanding balance is zero (completed loan)',
                $repaymentSummary['outstanding_balance'] == 0,
                'Outstanding balance: ' . $repaymentSummary['outstanding_balance'],
            );
        }

        $paidPlatformFees = (float) $loan->repayments()->where('status', 'paid')->sum('platform_fee');
        $checks[] = $this->check(
            'platform_revenue',
            'Platform revenue = paid platform_fee + penalties collected',
            abs($platformSummary['net_platform_revenue'] - ($paidPlatformFees + $repaymentSummary['paid_penalties'])) < 0.01,
            'Net revenue: ' . $platformSummary['net_platform_revenue'] . ', Paid platform fee: ' . round($paidPlatformFees, 2) . ', Penalties: ' . $repaymentSummary['paid_penalties'],
        );

        $moneyIn = $fundingSummary['total_received'] + $repaymentSummary['actual_repaid'] + $repaymentSummary['paid_penalties'];
        $moneyOut = $disbursedNet + $lenderTotalPaid;
        $expectedMoneyOut = $moneyOut + $platformSummary['net_platform_revenue'];

        $checks[] = $this->check(
            'money_equation',
            'Money In = Money Out + Platform Revenue',
            ! $hasDisbursement || abs($moneyIn - $expectedMoneyOut) < 0.01,
            $hasDisbursement
                ? 'Money In: ' . round($moneyIn, 2) . ', Money Out: ' . round($moneyOut, 2) . ', Platform Revenue: ' . $platformSummary['net_platform_revenue'] . ', Money Out + Revenue: ' . round($expectedMoneyOut, 2)
                : 'Money equation applies after disbursement (Money In: ' . round($moneyIn, 2) . ', held by platform)',
        );

        $checks[] = $this->check(
            'overpayment_protection',
            'No overpayment detected',
            $repaymentSummary['actual_repaid'] <= $totalRepayment + 0.01,
            'Actual repaid: ' . $repaymentSummary['actual_repaid'] . ', Total repayment: ' . $totalRepayment,
        );

        return $checks;
    }

    protected function check(string $key, string $label, bool $passed, string $detail): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'passed' => $passed,
            'detail' => $detail,
        ];
    }
}
