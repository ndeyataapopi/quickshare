<?php

namespace App\Modules\Funding\Services;

use App\Models\User;
use App\Modules\Funding\Models\Investment;
use App\Modules\Repayments\Models\LenderRepayment;
use Illuminate\Support\Collection;

class EarningsService
{
    // ─── Summary ────────────────────────────────────────────────────

    public function getLenderEarningsSummary(User $lender): array
    {
        $investments = Investment::forLender($lender->id);

        $totalInvested = (float) (clone $investments)
            ->whereIn('status', ['active', 'completed'])
            ->sum('amount');

        $totalExpectedReturn = (float) (clone $investments)
            ->whereIn('status', ['active', 'completed'])
            ->sum('expected_return');

        $totalActualReturn = (float) (clone $investments)
            ->whereIn('status', ['active', 'completed'])
            ->sum('actual_return');

        $totalEarnings = $totalActualReturn - $totalInvested;

        $activeCount = (clone $investments)->where('status', 'active')->count();
        $completedCount = (clone $investments)->where('status', 'completed')->count();

        $roi = $totalInvested > 0
            ? round(($totalEarnings / $totalInvested) * 100, 2)
            : 0.0;

        $monthlyAverage = $this->getMonthlyAverage($totalEarnings, $completedCount);

        $growthRate = $this->getEarningsGrowthRate($lender->id);

        return [
            'total_earnings'         => round($totalEarnings, 2),
            'total_actual_return'    => round($totalActualReturn, 2),
            'total_invested'         => round($totalInvested, 2),
            'total_expected_return'  => round($totalExpectedReturn, 2),
            'expected_profit'        => round($totalExpectedReturn - $totalInvested, 2),
            'active_count'           => $activeCount,
            'completed_count'        => $completedCount,
            'roi'                    => $roi,
            'monthly_average'        => round($monthlyAverage, 2),
            'growth_rate'            => $growthRate,
        ];
    }

    // ─── Portfolio Summary (alias for backward compat) ──────────────

    public function getLenderPortfolioSummary(User $lender): array
    {
        $summary = $this->getLenderEarningsSummary($lender);

        return [
            'total_invested'         => $summary['total_invested'],
            'total_expected_return'  => $summary['total_expected_return'],
            'total_actual_return'    => $summary['total_actual_return'],
            'active_investments'     => $summary['active_count'],
            'completed_investments'  => $summary['completed_count'],
            'pending_transactions'   => \App\Modules\Funding\Models\FundingTransaction::forLender($lender->id)
                ->where('status', 'pending')
                ->count(),
        ];
    }

    // ─── Chart Data: Earnings Overview ──────────────────────────────

    public function getEarningsOverviewData(User $lender, string $period = 'month'): array
    {
        $earnings = $lender->investments()
            ->where('status', 'completed')
            ->orderBy('completed_at', 'asc')
            ->get();

        if ($earnings->isEmpty()) {
            return ['labels' => ['No Data'], 'data' => [0]];
        }

        return match ($period) {
            'quarter' => $this->groupEarningsByQuarter($earnings),
            'year'    => $this->groupEarningsByYear($earnings),
            default   => $this->groupEarningsByMonth($earnings, 6),
        };
    }

    // ─── Chart Data: Earnings by Type ───────────────────────────────

    public function getEarningsByTypeData(User $lender): array
    {
        $earnings = $lender->investments()
            ->where('status', 'completed')
            ->with('loan')
            ->get();

        if ($earnings->isEmpty()) {
            return ['labels' => ['No Data'], 'data' => [100]];
        }

        $distribution = $earnings->groupBy(function ($item) {
            return $item->loan->purpose ?? 'Other';
        })->map(function ($group) {
            return $group->sum('actual_return');
        });

        return [
            'labels' => $distribution->keys()->toArray(),
            'data'   => $distribution->values()->toArray(),
        ];
    }

    // ─── Chart Data: Financial Overview ─────────────────────────────

    public function getFinancialOverviewData(User $user, string $period = 'month'): array
    {
        $loans = $user->loans()->orderBy('created_at', 'asc')->get();
        $investments = $user->investments()
            ->whereIn('status', ['active', 'completed'])
            ->orderBy('created_at', 'asc')
            ->get();

        return match ($period) {
            'quarter' => $this->groupFinancialByQuarter($loans, $investments),
            'year'    => $this->groupFinancialByYear($loans, $investments),
            default   => $this->groupFinancialByMonth($loans, $investments, 6),
        };
    }

    // ─── Chart Data: Portfolio Performance ──────────────────────────

    public function getPortfolioPerformanceData(User $lender): array
    {
        $investments = $lender->investments()
            ->whereIn('status', ['active', 'completed'])
            ->orderBy('created_at', 'asc')
            ->get();

        if ($investments->isEmpty()) {
            return [
                'labels'          => ['Now'],
                'portfolio_value' => [0],
                'total_invested'  => [0],
            ];
        }

        $labels = [];
        $portfolioValues = [];
        $totalInvestedValues = [];

        $cumulativeInvested = 0;
        $cumulativePortfolio = 0;

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();
            $labels[] = $date->format('M');

            $monthInvestments = $investments->filter(function ($item) use ($monthStart, $monthEnd) {
                return $item->created_at->between($monthStart, $monthEnd);
            });

            $cumulativeInvested += $monthInvestments->sum('amount');
            $cumulativePortfolio += $monthInvestments->sum('expected_return');

            $portfolioValues[] = round($cumulativePortfolio, 2);
            $totalInvestedValues[] = round($cumulativeInvested, 2);
        }

        return [
            'labels'          => $labels,
            'portfolio_value' => $portfolioValues,
            'total_invested'  => $totalInvestedValues,
        ];
    }

    // ─── Chart Data: Investment Distribution ────────────────────────

    public function getInvestmentDistributionData(User $lender): array
    {
        $investments = $lender->investments()
            ->whereIn('status', ['active', 'completed'])
            ->with('loan')
            ->get();

        if ($investments->isEmpty()) {
            return ['labels' => ['No Data'], 'data' => [100]];
        }

        $distribution = $investments->groupBy(function ($item) {
            return $item->loan->purpose ?? 'Other';
        })->map(function ($group) {
            return $group->sum('amount');
        });

        return [
            'labels' => $distribution->keys()->toArray(),
            'data'   => $distribution->values()->toArray(),
        ];
    }

    // ─── Chart Data: Investment Performance ─────────────────────────

    public function getInvestmentPerformanceData(User $lender): array
    {
        $investments = $lender->investments()
            ->whereIn('status', ['active', 'completed'])
            ->orderBy('created_at', 'asc')
            ->get();

        if ($investments->isEmpty()) {
            return [
                'labels'    => ['Q1', 'Q2', 'Q3', 'Q4'],
                'invested'  => [0, 0, 0, 0],
                'returns'   => [0, 0, 0, 0],
            ];
        }

        $quarterlyData = $investments->groupBy(function ($item) {
            return 'Q' . ceil($item->created_at->month / 3);
        })->map(function ($group) {
            return [
                'invested' => $group->sum('amount'),
                'returns'  => $group->where('status', 'completed')->sum('actual_return'),
            ];
        });

        $labels = ['Q1', 'Q2', 'Q3', 'Q4'];
        $invested = [];
        $returns = [];

        foreach ($labels as $quarter) {
            $invested[] = $quarterlyData[$quarter]['invested'] ?? 0;
            $returns[] = $quarterlyData[$quarter]['returns'] ?? 0;
        }

        return [
            'labels'   => $labels,
            'invested' => $invested,
            'returns'  => $returns,
        ];
    }

    // ─── Per-investment ROI ─────────────────────────────────────────

    public function getInvestmentRoi(Investment $investment): float
    {
        if ($investment->amount <= 0) {
            return 0.0;
        }

        return round((($investment->actual_return - $investment->amount) / $investment->amount) * 100, 2);
    }

    // ─── Admin: Platform Earnings ───────────────────────────────────

    public function getPlatformEarningsSummary(): array
    {
        $investments = Investment::query();

        $totalInvested = (float) (clone $investments)
            ->whereIn('status', ['active', 'completed'])
            ->sum('amount');

        $totalActualReturn = (float) (clone $investments)
            ->whereIn('status', ['active', 'completed'])
            ->sum('actual_return');

        $totalEarnings = $totalActualReturn - $totalInvested;

        $totalExpectedReturn = (float) (clone $investments)
            ->whereIn('status', ['active', 'completed'])
            ->sum('expected_return');

        $activeCount = (clone $investments)->where('status', 'active')->count();
        $completedCount = (clone $investments)->where('status', 'completed')->count();

        $roi = $totalInvested > 0
            ? round(($totalEarnings / $totalInvested) * 100, 2)
            : 0.0;

        return [
            'total_invested'        => round($totalInvested, 2),
            'total_expected_return' => round($totalExpectedReturn, 2),
            'total_actual_return'   => round($totalActualReturn, 2),
            'total_earnings'        => round($totalEarnings, 2),
            'active_investments'    => $activeCount,
            'completed_investments' => $completedCount,
            'roi'                   => $roi,
        ];
    }

    // ─── Internal Helpers ───────────────────────────────────────────

    protected function getMonthlyAverage(float $totalEarnings, int $completedCount): float
    {
        if ($completedCount === 0) {
            return 0.0;
        }

        $earliestCompleted = Investment::where('status', 'completed')
            ->orderBy('completed_at', 'asc')
            ->value('completed_at');

        if (!$earliestCompleted) {
            return 0.0;
        }

        $months = max(1, $earliestCompleted->diffInMonths(now()));

        return $totalEarnings / $months;
    }

    protected function getEarningsGrowthRate(int $lenderId): float
    {
        $thisMonth = LenderRepayment::forLender($lenderId)
            ->processed()
            ->where('processed_at', '>=', now()->startOfMonth())
            ->sum('interest_earned');

        $lastMonth = LenderRepayment::forLender($lenderId)
            ->processed()
            ->where('processed_at', '>=', now()->subMonth()->startOfMonth())
            ->where('processed_at', '<', now()->startOfMonth())
            ->sum('interest_earned');

        if ($lastMonth <= 0) {
            return $thisMonth > 0 ? 100.0 : 0.0;
        }

        return round((($thisMonth - $lastMonth) / $lastMonth) * 100, 2);
    }

    protected function groupEarningsByMonth(Collection $earnings, int $months): array
    {
        $monthlyData = $earnings->groupBy(function ($item) {
            return $item->completed_at->format('M Y');
        })->map(function ($group) {
            return $group->sum('actual_return');
        });

        $labels = [];
        $data = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $key = $date->format('M Y');
            $labels[] = $date->format('M');
            $data[] = $monthlyData[$key] ?? 0;
        }

        return ['labels' => $labels, 'data' => $data];
    }

    protected function groupEarningsByQuarter(Collection $earnings): array
    {
        $quarterlyData = $earnings->groupBy(function ($item) {
            return 'Q' . ceil($item->completed_at->month / 3) . ' ' . $item->completed_at->year;
        })->map(function ($group) {
            return $group->sum('actual_return');
        });

        $labels = [];
        $data = [];

        for ($i = 3; $i >= 0; $i--) {
            $date = now()->subQuarters($i);
            $key = 'Q' . ceil($date->month / 3) . ' ' . $date->year;
            $labels[] = 'Q' . ceil($date->month / 3);
            $data[] = $quarterlyData[$key] ?? 0;
        }

        return ['labels' => $labels, 'data' => $data];
    }

    protected function groupEarningsByYear(Collection $earnings): array
    {
        $yearlyData = $earnings->groupBy(function ($item) {
            return $item->completed_at->year;
        })->map(function ($group) {
            return $group->sum('actual_return');
        });

        $labels = [];
        $data = [];

        for ($i = 4; $i >= 0; $i--) {
            $year = now()->year - $i;
            $labels[] = (string) $year;
            $data[] = $yearlyData[$year] ?? 0;
        }

        return ['labels' => $labels, 'data' => $data];
    }

    protected function groupFinancialByMonth(Collection $loans, Collection $investments, int $months): array
    {
        $labels = [];
        $borrowedData = [];
        $investedData = [];
        $earningsData = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $labels[] = $date->format('M');

            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();

            $borrowedData[] = $loans->filter(function ($loan) use ($monthStart, $monthEnd) {
                return $loan->created_at->between($monthStart, $monthEnd);
            })->sum('approved_amount');

            $investedData[] = $investments->filter(function ($inv) use ($monthStart, $monthEnd) {
                return $inv->created_at->between($monthStart, $monthEnd);
            })->sum('amount');

            $earningsData[] = $investments->filter(function ($inv) use ($monthStart, $monthEnd) {
                return $inv->status === 'completed'
                    && $inv->completed_at
                    && $inv->completed_at->between($monthStart, $monthEnd);
            })->sum(function ($inv) {
                return $inv->actual_return - $inv->amount;
            });
        }

        return [
            'labels'   => $labels,
            'borrowed' => $borrowedData,
            'invested' => $investedData,
            'earnings' => $earningsData,
        ];
    }

    protected function groupFinancialByQuarter(Collection $loans, Collection $investments): array
    {
        $labels = [];
        $borrowedData = [];
        $investedData = [];
        $earningsData = [];

        for ($i = 3; $i >= 0; $i--) {
            $date = now()->subQuarters($i);
            $labels[] = 'Q' . ceil($date->month / 3);

            $qStart = $date->copy()->startOfQuarter();
            $qEnd = $date->copy()->endOfQuarter();

            $borrowedData[] = $loans->filter(function ($loan) use ($qStart, $qEnd) {
                return $loan->created_at->between($qStart, $qEnd);
            })->sum('approved_amount');

            $investedData[] = $investments->filter(function ($inv) use ($qStart, $qEnd) {
                return $inv->created_at->between($qStart, $qEnd);
            })->sum('amount');

            $earningsData[] = $investments->filter(function ($inv) use ($qStart, $qEnd) {
                return $inv->status === 'completed'
                    && $inv->completed_at
                    && $inv->completed_at->between($qStart, $qEnd);
            })->sum(function ($inv) {
                return $inv->actual_return - $inv->amount;
            });
        }

        return [
            'labels'   => $labels,
            'borrowed' => $borrowedData,
            'invested' => $investedData,
            'earnings' => $earningsData,
        ];
    }

    protected function groupFinancialByYear(Collection $loans, Collection $investments): array
    {
        $labels = [];
        $borrowedData = [];
        $investedData = [];
        $earningsData = [];

        for ($i = 4; $i >= 0; $i--) {
            $year = now()->year - $i;
            $labels[] = (string) $year;

            $yStart = now()->copy()->startOfYear()->subYears($i);
            $yEnd = $yStart->copy()->endOfYear();

            $borrowedData[] = $loans->filter(function ($loan) use ($yStart, $yEnd) {
                return $loan->created_at->between($yStart, $yEnd);
            })->sum('approved_amount');

            $investedData[] = $investments->filter(function ($inv) use ($yStart, $yEnd) {
                return $inv->created_at->between($yStart, $yEnd);
            })->sum('amount');

            $earningsData[] = $investments->filter(function ($inv) use ($yStart, $yEnd) {
                return $inv->status === 'completed'
                    && $inv->completed_at
                    && $inv->completed_at->between($yStart, $yEnd);
            })->sum(function ($inv) {
                return $inv->actual_return - $inv->amount;
            });
        }

        return [
            'labels'   => $labels,
            'borrowed' => $borrowedData,
            'invested' => $investedData,
            'earnings' => $earningsData,
        ];
    }
}
