<?php

namespace App\Services;

use App\Models\User;
use App\Modules\Loans\Models\Loan;
use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\Repayments\Models\Repayment;
use App\Modules\KYC\Models\KycSubmission;
use Illuminate\Support\Facades\Cache;

class PlatformStatisticsService
{
    public function getStatistics(): array
    {
        return Cache::remember('platform.statistics', now()->addMinutes(5), function () {
            $totalFunded = $this->totalFundedAmount();

            return [
                'total_users' => $this->totalUsers(),
                'verified_users' => $this->verifiedUsers(),
                'active_lenders' => $this->activeLenders(),
                'active_borrowers' => $this->activeBorrowers(),
                'total_loans' => $this->totalLoans(),
                'loans_funded' => $this->loansFunded(),
                'total_funded_amount' => $totalFunded,
                'total_funded_formatted' => $this->formatCompactCurrency($totalFunded),
                'total_repaid_amount' => $this->totalRepaidAmount(),
                'repayment_rate' => $this->repaymentRate(),
                'average_trust_score' => $this->averageTrustScore(),
            ];
        });
    }

    public function totalUsers(): int
    {
        return Cache::remember('platform.stats.total_users', now()->addMinutes(5), fn () => User::count());
    }

    public function verifiedUsers(): int
    {
        return Cache::remember('platform.stats.verified_users', now()->addMinutes(5), function () {
            return KycSubmission::where('status', 'approved')->distinct('user_id')->count('user_id');
        });
    }

    public function activeLenders(): int
    {
        return Cache::remember('platform.stats.active_lenders', now()->addMinutes(5), function () {
            return FundingTransaction::where('status', 'confirmed')->distinct('lender_id')->count('lender_id');
        });
    }

    public function activeBorrowers(): int
    {
        return Cache::remember('platform.stats.active_borrowers', now()->addMinutes(5), function () {
            return Loan::whereIn('status', ['active', 'disbursed', 'completed'])
                ->distinct('borrower_id')
                ->count('borrower_id');
        });
    }

    public function totalLoans(): int
    {
        return Cache::remember('platform.stats.total_loans', now()->addMinutes(5), fn () => Loan::count());
    }

    public function loansFunded(): int
    {
        return Cache::remember('platform.stats.loans_funded', now()->addMinutes(5), function () {
            return Loan::whereIn('status', [
                'funded',
                'awaiting_disbursement',
                'disbursed',
                'active',
                'completed',
            ])->count();
        });
    }

    public function totalFundedAmount(): float
    {
        return Cache::remember('platform.stats.total_funded_amount', now()->addMinutes(5), function () {
            return (float) FundingTransaction::where('status', 'confirmed')->sum('amount');
        });
    }

    public function totalRepaidAmount(): float
    {
        return Cache::remember('platform.stats.total_repaid_amount', now()->addMinutes(5), function () {
            return (float) Repayment::where('status', 'paid')->sum('amount');
        });
    }

    public function repaymentRate(): float
    {
        return Cache::remember('platform.stats.repayment_rate', now()->addMinutes(5), function () {
            $totalScheduled = Repayment::whereIn('status', ['paid', 'partial', 'overdue', 'defaulted'])->count();
            $totalPaid = Repayment::where('status', 'paid')->count();

            if ($totalScheduled === 0) {
                return 0.0;
            }

            return round(($totalPaid / $totalScheduled) * 100, 1);
        });
    }

    public function averageTrustScore(): float
    {
        return Cache::remember('platform.stats.avg_trust_score', now()->addMinutes(5), function () {
            return (float) User::where('status', 'active')->avg('trust_score') ?? 0;
        });
    }

    public function formatCurrency(float $amount): string
    {
        $symbol = config('loan.general.currency_symbol', 'N$');

        return $symbol . ' ' . number_format($amount, 2, '.', ',');
    }

    public function formatCompactCurrency(float $amount): string
    {
        $symbol = config('loan.general.currency_symbol', 'N$');

        if ($amount >= 1000000) {
            return $symbol . number_format($amount / 1000000, 1) . 'M';
        }

        if ($amount >= 1000) {
            return $symbol . number_format($amount / 1000, 1) . 'K';
        }

        return $symbol . number_format($amount, 0);
    }
}
