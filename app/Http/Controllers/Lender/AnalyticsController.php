<?php

namespace App\Http\Controllers\Lender;

use App\Http\Controllers\Controller;
use App\Modules\Funding\Services\EarningsService;
use App\Modules\TrustScore\Models\TrustScoreHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AnalyticsController extends Controller
{
    public function __construct(protected EarningsService $earningsService)
    {
    }

    public function index()
    {
        $user = Auth::user();

        $earningsSummary = $this->earningsService->getLenderEarningsSummary($user);

        $financialData = $this->earningsService->getFinancialOverviewData($user, 'month');
        $financialDataQuarter = $this->earningsService->getFinancialOverviewData($user, 'quarter');
        $financialDataYear = $this->earningsService->getFinancialOverviewData($user, 'year');

        $loanStatusData = $this->getLoanStatusDistributionData($user);
        $trustScoreData = $this->getTrustScoreHistoryData($user);
        $investmentData = $this->earningsService->getInvestmentPerformanceData($user);

        $totalLoans = $user->loans()->count();
        $activeLoans = $user->loans()->whereIn('status', ['active', 'disbursed'])->count();
        $completedLoans = $user->loans()->where('status', 'completed')->count();
        $defaultedLoans = $user->loans()->where('status', 'defaulted')->count();
        $totalBorrowed = $user->loans()->whereNotNull('approved_amount')->sum('approved_amount');
        $totalRepaid = $user->repayments()->where('status', 'paid')->sum('amount');
        $score = (float) $user->trust_score;
        $tier = \App\Modules\TrustScore\Services\TrustScoreService::getTier($score);
        $repaymentRate = $totalLoans > 0 ? round(($completedLoans / $totalLoans) * 100, 1) : 0;

        $recentLoans = $user->loans()->latest()->take(5)->get();
        $recentInvestments = $user->investments()->with('loan')->latest()->take(5)->get();
        $recentRepayments = $user->repayments()->with('loan')->latest()->take(5)->get();

        return view('client.analytics', compact(
            'earningsSummary',
            'financialData',
            'financialDataQuarter',
            'financialDataYear',
            'loanStatusData',
            'trustScoreData',
            'investmentData',
            'totalLoans',
            'activeLoans',
            'completedLoans',
            'defaultedLoans',
            'totalBorrowed',
            'totalRepaid',
            'score',
            'tier',
            'repaymentRate',
            'recentLoans',
            'recentInvestments',
            'recentRepayments'
        ));
    }

    private function getLoanStatusDistributionData($user)
    {
        $loans = $user->loans()->get();

        $active = $loans->where('status', 'active')->count();
        $completed = $loans->where('status', 'completed')->count();
        $pending = $loans->whereIn('status', ['pending_review', 'marketplace'])->count();
        $defaulted = $loans->where('status', 'defaulted')->count();

        return [
            'labels' => ['Active', 'Completed', 'Pending', 'Defaulted'],
            'data' => [$active, $completed, $pending, $defaulted],
        ];
    }

    private function getTrustScoreHistoryData($user)
    {
        $scoreHistory = TrustScoreHistory::forUser($user->id)
            ->orderBy('created_at', 'asc')
            ->take(10)
            ->get();

        if ($scoreHistory->isEmpty()) {
            return [
                'labels' => ['Now'],
                'data' => [(float) $user->trust_score],
            ];
        }

        $labels = $scoreHistory->map(function($h) {
            return $h->created_at->format('M');
        })->toArray();

        $data = $scoreHistory->map(function($h) {
            return (float) $h->new_score;
        })->toArray();

        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }
}
