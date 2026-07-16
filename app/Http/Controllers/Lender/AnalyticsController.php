<?php

namespace App\Http\Controllers\Lender;

use App\Http\Controllers\Controller;
use App\Modules\TrustScore\Models\TrustScoreHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AnalyticsController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        // Get real financial overview data
        $financialData = $this->getFinancialOverviewData($user);
        
        // Get real loan status distribution data
        $loanStatusData = $this->getLoanStatusDistributionData($user);
        
        // Get real trust score history data
        $trustScoreData = $this->getTrustScoreHistoryData($user);
        
        // Get real investment performance data
        $investmentData = $this->getInvestmentPerformanceData($user);
        
        return view('client.analytics', compact(
            'financialData',
            'loanStatusData',
            'trustScoreData',
            'investmentData'
        ));
    }
    
    private function getFinancialOverviewData($user)
    {
        $loans = $user->loans()->orderBy('created_at', 'asc')->get();
        $investments = $user->investments()->whereIn('status', ['active', 'completed'])->orderBy('created_at', 'asc')->get();
        
        // Get last 6 months of data
        $labels = [];
        $borrowedData = [];
        $investedData = [];
        $earningsData = [];
        
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $labels[] = $date->format('M');
            
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();
            
            // Calculate borrowed amount for this month
            $borrowed = $loans->filter(function($loan) use ($monthStart, $monthEnd) {
                return $loan->created_at->between($monthStart, $monthEnd);
            })->sum('approved_amount');
            
            // Calculate invested amount for this month
            $invested = $investments->filter(function($inv) use ($monthStart, $monthEnd) {
                return $inv->created_at->between($monthStart, $monthEnd);
            })->sum('amount');
            
            // Calculate earnings for this month (completed investments)
            $earnings = $investments->filter(function($inv) use ($monthStart, $monthEnd) {
                return $inv->status === 'completed' && 
                       $inv->completed_at && 
                       $inv->completed_at->between($monthStart, $monthEnd);
            })->sum(function($inv) {
                return $inv->actual_return - $inv->amount;
            });
            
            $borrowedData[] = $borrowed;
            $investedData[] = $invested;
            $earningsData[] = $earnings;
        }
        
        return [
            'labels' => $labels,
            'borrowed' => $borrowedData,
            'invested' => $investedData,
            'earnings' => $earningsData,
        ];
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
    
    private function getInvestmentPerformanceData($user)
    {
        $investments = $user->investments()
            ->whereIn('status', ['active', 'completed'])
            ->orderBy('created_at', 'asc')
            ->get();
        
        if ($investments->isEmpty()) {
            return [
                'labels' => ['Q1', 'Q2', 'Q3', 'Q4'],
                'invested' => [0, 0, 0, 0],
                'returns' => [0, 0, 0, 0],
            ];
        }
        
        // Group by quarter
        $quarterlyData = $investments->groupBy(function($item) {
            return 'Q' . ceil($item->created_at->month / 3);
        })->map(function($group) {
            return [
                'invested' => $group->sum('amount'),
                'returns' => $group->where('status', 'completed')->sum('actual_return'),
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
            'labels' => $labels,
            'invested' => $invested,
            'returns' => $returns,
        ];
    }
}
