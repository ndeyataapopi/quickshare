<?php

namespace App\Http\Controllers\Lender;

use App\Http\Controllers\Controller;
use App\Modules\Funding\Models\Investment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvestmentController extends Controller
{
    public function index()
    {
        $investments = Auth::user()->investments()
            ->with('loan.borrower')
            ->latest()
            ->paginate(20);
        $summary = [
            'total_invested'   => Auth::user()->investments()->whereIn('status', ['active','completed'])->sum('amount'),
            'total_expected'   => Auth::user()->investments()->whereIn('status', ['active','completed'])->sum('expected_return'),
            'total_actual'     => Auth::user()->investments()->where('status', 'completed')->sum('actual_return'),
            'active_count'     => Auth::user()->investments()->where('status', 'active')->count(),
            'completed_count'  => Auth::user()->investments()->where('status', 'completed')->count(),
        ];
        
        // Get real portfolio performance data
        $portfolioData = $this->getPortfolioPerformanceData();
        
        // Get real investment distribution data
        $distributionData = $this->getInvestmentDistributionData();
        
        return view('client.investments.index', compact('investments', 'summary', 'portfolioData', 'distributionData'));
    }
    
    private function getPortfolioPerformanceData()
    {
        $user = Auth::user();
        $investments = $user->investments()
            ->whereIn('status', ['active', 'completed'])
            ->orderBy('created_at', 'asc')
            ->get();
        
        if ($investments->isEmpty()) {
            return [
                'labels' => ['Now'],
                'portfolio_value' => [0],
                'total_invested' => [0],
            ];
        }
        
        // Group by month for the last 6 months
        $monthlyData = $investments->groupBy(function($item) {
            return $item->created_at->format('M Y');
        })->map(function($group) {
            return [
                'portfolio_value' => $group->sum('expected_return'),
                'total_invested' => $group->sum('amount'),
            ];
        });
        
        // Get last 6 months of data
        $labels = [];
        $portfolioValues = [];
        $totalInvested = [];
        
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $key = $date->format('M Y');
            $labels[] = $date->format('M');
            $portfolioValues[] = $monthlyData[$key]['portfolio_value'] ?? 0;
            $totalInvested[] = $monthlyData[$key]['total_invested'] ?? 0;
        }
        
        return [
            'labels' => $labels,
            'portfolio_value' => $portfolioValues,
            'total_invested' => $totalInvested,
        ];
    }
    
    private function getInvestmentDistributionData()
    {
        $user = Auth::user();
        $investments = $user->investments()
            ->whereIn('status', ['active', 'completed'])
            ->with('loan')
            ->get();
        
        if ($investments->isEmpty()) {
            return [
                'labels' => ['No Data'],
                'data' => [100],
            ];
        }
        
        // Group by loan purpose
        $distribution = $investments->groupBy(function($item) {
            return $item->loan->purpose ?? 'Other';
        })->map(function($group) {
            return $group->sum('amount');
        });
        
        return [
            'labels' => $distribution->keys()->toArray(),
            'data' => $distribution->values()->toArray(),
        ];
    }

    public function show(Investment $investment)
    {
        $this->authorize('view', $investment);
        return view('client.investments.show', compact('investment'));
    }
}
