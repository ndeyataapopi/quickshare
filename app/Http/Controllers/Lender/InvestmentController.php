<?php

namespace App\Http\Controllers\Lender;

use App\Http\Controllers\Controller;
use App\Modules\Funding\Models\Investment;
use App\Modules\Funding\Services\EarningsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvestmentController extends Controller
{
    public function __construct(protected EarningsService $earningsService)
    {
    }

    public function index()
    {
        $user = Auth::user();

        $investments = $user->investments()
            ->with('loan.borrower')
            ->latest()
            ->paginate(20);

        $portfolioSummary = $this->earningsService->getLenderPortfolioSummary($user);
        $summary = [
            'total_invested'   => $portfolioSummary['total_invested'],
            'total_expected'   => $portfolioSummary['total_expected_return'],
            'total_actual'     => $portfolioSummary['total_actual_return'],
            'active_count'     => $portfolioSummary['active_investments'],
            'completed_count'  => $portfolioSummary['completed_investments'],
        ];

        $portfolioData = $this->earningsService->getPortfolioPerformanceData($user);
        $distributionData = $this->earningsService->getInvestmentDistributionData($user);

        return view('client.investments.index', compact('investments', 'summary', 'portfolioData', 'distributionData'));
    }

    public function show(Investment $investment)
    {
        $this->authorize('view', $investment);
        return view('client.investments.show', compact('investment'));
    }
}
