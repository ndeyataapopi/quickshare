<?php

namespace App\Modules\Admin\Services;

use App\Models\User;
use App\Modules\Collections\Models\CollectionLog;
use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\KYC\Models\KycSubmission;
use App\Modules\Loans\Models\Loan;
use App\Modules\Repayments\Models\Repayment;
use Illuminate\Support\Facades\DB;

class AdminDashboardService
{
    // ─── Main Dashboard Metrics ────────────────────────────────────────

    public function getDashboardMetrics(): array
    {
        return [
            'overview' => $this->getOverviewStats(),
            'kyc' => $this->getKycStats(),
            'loans' => $this->getLoanStats(),
            'funding' => $this->getFundingStats(),
            'repayments' => $this->getRepaymentStats(),
            'collections' => $this->getCollectionsStats(),
            'users' => $this->getUserStats(),
            'revenue' => $this->getRevenueStats(),
            'charts' => $this->getChartData(),
        ];
    }

    // ─── Overview Stats ──────────────────────────────────────────────

    public function getOverviewStats(): array
    {
        return [
            'total_loans' => Loan::count(),
            'active_loans' => Loan::where('status', 'active')->count(),
            'total_users' => User::count(),
            'active_users' => User::where('status', 'active')->count(),
            'total_repayments' => Repayment::where('status', 'paid')->count(),
            'pending_kyc' => KycSubmission::where('status', 'pending')->count(),
            'pending_loans' => Loan::where('status', 'pending_review')->count(),
            'overdue_loans' => Loan::where('status', 'overdue')->count(),
            'defaulted_loans' => Loan::where('status', 'defaulted')->count(),
        ];
    }

    // ─── KYC Stats ─────────────────────────────────────────────────────

    public function getKycStats(): array
    {
        return [
            'pending' => KycSubmission::where('status', 'pending')->count(),
            'approved' => KycSubmission::where('status', 'approved')->count(),
            'rejected' => KycSubmission::where('status', 'rejected')->count(),
            'avg_processing_time' => $this->getAvgKycProcessingTime(),
            'today_submissions' => KycSubmission::whereDate('created_at', today())->count(),
            'today_approved' => KycSubmission::where('status', 'approved')
                ->whereDate('updated_at', today())
                ->count(),
        ];
    }

    protected function getAvgKycProcessingTime(): ?float
    {
        $avgHours = KycSubmission::where('status', 'approved')
            ->whereNotNull('reviewed_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, reviewed_at)) as avg_hours')
            ->value('avg_hours');

        return $avgHours ? round($avgHours, 1) : null;
    }

    // ─── Loan Stats ────────────────────────────────────────────────────

    public function getLoanStats(): array
    {
        $loans = Loan::query();

        return [
            'by_status' => [
                'draft' => (clone $loans)->where('status', 'draft')->count(),
                'pending_review' => (clone $loans)->where('status', 'pending_review')->count(),
                'marketplace' => (clone $loans)->where('status', 'marketplace')->count(),
                'partially_funded' => (clone $loans)->where('status', 'partially_funded')->count(),
                'funded' => (clone $loans)->where('status', 'funded')->count(),
                'awaiting_disbursement' => (clone $loans)->where('status', 'awaiting_disbursement')->count(),
                'active' => (clone $loans)->where('status', 'active')->count(),
                'overdue' => (clone $loans)->where('status', 'overdue')->count(),
                'completed' => (clone $loans)->where('status', 'completed')->count(),
                'defaulted' => (clone $loans)->where('status', 'defaulted')->count(),
                'cancelled' => (clone $loans)->where('status', 'cancelled')->count(),
            ],
            'total_requested' => round((clone $loans)->sum('requested_amount'), 2),
            'total_approved' => round((clone $loans)->sum('approved_amount'), 2),
            'total_funded' => round((clone $loans)->sum('funded_amount'), 2),
            'avg_loan_amount' => round((clone $loans)->avg('approved_amount') ?? 0, 2),
            'avg_interest_rate' => round((clone $loans)->avg('interest_rate') ?? 0, 2),
        ];
    }

    // ─── Funding Stats ───────────────────────────────────────────────

    public function getFundingStats(): array
    {
        $fundings = FundingTransaction::confirmed();

        return [
            'total_transactions' => (clone $fundings)->count(),
            'total_invested' => round((clone $fundings)->sum('amount'), 2),
            'avg_investment' => round((clone $fundings)->avg('amount') ?? 0, 2),
            'active_lenders' => FundingTransaction::confirmed()->distinct('lender_id')->count('lender_id'),
            'pending_transactions' => FundingTransaction::where('status', 'pending')->count(),
            'today_investments' => FundingTransaction::confirmed()
                ->whereDate('confirmed_at', today())
                ->sum('amount'),
        ];
    }

    // ─── Repayment Stats ───────────────────────────────────────────────

    public function getRepaymentStats(): array
    {
        $repayments = Repayment::query();

        return [
            'by_status' => [
                'pending' => (clone $repayments)->where('status', 'pending')->count(),
                'partial' => (clone $repayments)->where('status', 'partial')->count(),
                'paid' => (clone $repayments)->where('status', 'paid')->count(),
                'overdue' => (clone $repayments)->where('status', 'overdue')->count(),
                'defaulted' => (clone $repayments)->where('status', 'defaulted')->count(),
            ],
            'total_expected' => round((clone $repayments)->sum('amount'), 2),
            'total_paid' => round((clone $repayments)->where('status', 'paid')->sum('amount'), 2),
            'total_penalties' => round((clone $repayments)->sum('penalty'), 2),
            'collection_rate' => $this->calculateCollectionRate(),
            'today_payments' => (clone $repayments)
                ->where('status', 'paid')
                ->whereDate('paid_date', today())
                ->sum('amount'),
        ];
    }

    protected function calculateCollectionRate(): float
    {
        $expected = Repayment::sum('amount');
        $paid = Repayment::where('status', 'paid')->sum('amount');

        if ($expected <= 0) {
            return 100.0;
        }

        return round(($paid / $expected) * 100, 2);
    }

    // ─── Collections Stats ───────────────────────────────────────────

    public function getCollectionsStats(): array
    {
        return [
            'reminders_today' => CollectionLog::reminders()->whereDate('created_at', today())->count(),
            'reminders_delivered' => CollectionLog::reminders()->delivered()->whereDate('created_at', today())->count(),
            'escalations_level_1' => CollectionLog::where('action_type', 'escalation_level_1')
                ->whereDate('created_at', today())
                ->count(),
            'escalations_level_2' => CollectionLog::where('action_type', 'escalation_level_2')
                ->whereDate('created_at', today())
                ->count(),
            'escalations_level_3' => CollectionLog::where('action_type', 'escalation_level_3')
                ->whereDate('created_at', today())
                ->count(),
            'delivery_rate' => $this->calculateDeliveryRate(),
            'response_rate' => $this->calculateResponseRate(),
        ];
    }

    protected function calculateDeliveryRate(): float
    {
        $total = CollectionLog::reminders()->whereDate('created_at', today())->count();
        if ($total === 0) {
            return 100.0;
        }

        $delivered = CollectionLog::reminders()
            ->delivered()
            ->whereDate('created_at', today())
            ->count();

        return round(($delivered / $total) * 100, 1);
    }

    protected function calculateResponseRate(): float
    {
        $total = CollectionLog::reminders()->whereDate('created_at', today())->count();
        if ($total === 0) {
            return 0.0;
        }

        $responded = CollectionLog::reminders()
            ->where('response_received', true)
            ->whereDate('created_at', today())
            ->count();

        return round(($responded / $total) * 100, 1);
    }

    // ─── User Stats ──────────────────────────────────────────────────

    public function getUserStats(): array
    {
        return [
            'total' => User::count(),
            'by_status' => [
                'active' => User::where('status', 'active')->count(),
                'pending' => User::where('status', 'pending')->count(),
                'suspended' => User::where('status', 'suspended')->count(),
                'inactive' => User::where('status', 'inactive')->count(),
            ],
            'by_role' => [
                'borrowers' => User::role('borrower')->count(),
                'lenders' => User::role('lender')->count(),
                'admins' => User::role('admin')->count(),
            ],
            'new_today' => User::whereDate('created_at', today())->count(),
            'new_this_week' => User::whereDate('created_at', '>=', now()->subDays(7))->count(),
            'new_this_month' => User::whereDate('created_at', '>=', now()->subDays(30))->count(),
        ];
    }

    // ─── Revenue Stats ─────────────────────────────────────────────────

    public function getRevenueStats(): array
    {
        $platformFees = Loan::sum('platform_fee');
        $penalties = Repayment::sum('penalty');

        return [
            'total_platform_fees' => round($platformFees, 2),
            'total_penalties' => round($penalties, 2),
            'total_revenue' => round($platformFees + $penalties, 2),
            'revenue_this_month' => round($this->getMonthlyRevenue(), 2),
            'revenue_today' => round($this->getDailyRevenue(), 2),
            'projected_monthly' => round($this->getProjectedMonthlyRevenue(), 2),
        ];
    }

    protected function getMonthlyRevenue(): float
    {
        $fees = Loan::whereDate('disbursed_at', '>=', now()->subDays(30))
            ->sum('platform_fee');
        
        $penalties = Repayment::whereDate('paid_date', '>=', now()->subDays(30))
            ->sum('penalty');

        return $fees + $penalties;
    }

    protected function getDailyRevenue(): float
    {
        $fees = Loan::whereDate('disbursed_at', today())
            ->sum('platform_fee');
        
        $penalties = Repayment::whereDate('paid_date', today())
            ->sum('penalty');

        return $fees + $penalties;
    }

    protected function getProjectedMonthlyRevenue(): float
    {
        // Calculate based on active loan volume
        $activeLoanVolume = Loan::whereIn('status', ['active', 'disbursed'])
            ->sum('approved_amount');
        
        $avgPlatformFeeRate = 0.03; // 3%
        
        return $activeLoanVolume * $avgPlatformFeeRate;
    }

    // ─── Chart Data ──────────────────────────────────────────────────

    public function getChartData(): array
    {
        return [
            'loans_over_time' => $this->getLoansOverTime(),
            'repayments_over_time' => $this->getRepaymentsOverTime(),
            'user_growth' => $this->getUserGrowth(),
            'revenue_by_month' => $this->getRevenueByMonth(),
        ];
    }

    protected function getLoansOverTime(): array
    {
        return Loan::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(approved_amount) as amount')
        )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    protected function getRepaymentsOverTime(): array
    {
        return Repayment::where('status', 'paid')
            ->select(
                DB::raw('DATE(paid_date) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as amount')
            )
            ->where('paid_date', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    protected function getUserGrowth(): array
    {
        return User::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count')
        )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    protected function getRevenueByMonth(): array
    {
        return DB::table('loans')
            ->select(
                DB::raw('DATE_FORMAT(disbursed_at, "%Y-%m") as month'),
                DB::raw('SUM(platform_fee) as platform_fees')
            )
            ->whereNotNull('disbursed_at')
            ->where('disbursed_at', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->toArray();
    }

    // ─── Recent Activity ─────────────────────────────────────────────

    public function getRecentActivity(int $limit = 10): array
    {
        return [
            'recent_kyc' => KycSubmission::with('user:id,first_name,last_name')
                ->latest()
                ->limit($limit)
                ->get(),
            'recent_loans' => Loan::with('borrower:id,first_name,last_name')
                ->latest()
                ->limit($limit)
                ->get(),
            'recent_repayments' => Repayment::with(['borrower:id,first_name,last_name', 'loan:id,reference'])
                ->where('status', 'paid')
                ->latest('paid_date')
                ->limit($limit)
                ->get(),
            'recent_users' => User::latest()
                ->limit($limit)
                ->get(),
        ];
    }
}
