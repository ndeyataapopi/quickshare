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

        return view('client.analytics', compact(
            'earningsSummary',
            'financialData',
            'financialDataQuarter',
            'financialDataYear',
            'loanStatusData',
            'trustScoreData',
            'investmentData'
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
