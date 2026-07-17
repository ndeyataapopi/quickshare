<?php

namespace App\Http\Controllers\Lender;

use App\Http\Controllers\Controller;
use App\Modules\Funding\Models\Investment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EarningsController extends Controller
{
    public function index()
    {
        $earnings      = Auth::user()->investments()->with('loan')->where('status', 'completed')->latest('completed_at')->paginate(20);
        $totalEarnings = Auth::user()->investments()->where('status', 'completed')->sum('actual_return');
        $totalInvested = Auth::user()->investments()->whereIn('status', ['active', 'completed'])->sum('amount');
        $activeCount   = Auth::user()->investments()->where('status', 'active')->count();
        
        // Get real earnings overview data
        $earningsData = $this->getEarningsOverviewData();
        
        // Get real earnings by type data
        $earningsTypeData = $this->getEarningsByTypeData();
        
        return view('client.earnings.index', compact('earnings', 'totalEarnings', 'totalInvested', 'activeCount', 'earningsData', 'earningsTypeData'));
    }
    
    private function getEarningsOverviewData()
    {
        $user = Auth::user();
        $earnings = $user->investments()
            ->where('status', 'completed')
            ->orderBy('completed_at', 'asc')
            ->get();
        
        if ($earnings->isEmpty()) {
            return [
                'labels' => ['Now'],
                'data' => [0],
            ];
        }
        
        // Group by month for the last 6 months
        $monthlyData = $earnings->groupBy(function($item) {
            return $item->completed_at->format('M Y');
        })->map(function($group) {
            return $group->sum('actual_return');
        });
        
        // Get last 6 months of data
        $labels = [];
        $data = [];
        
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $key = $date->format('M Y');
            $labels[] = $date->format('M');
            $data[] = $monthlyData[$key] ?? 0;
        }
        
        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }
    
    private function getEarningsByTypeData()
    {
        $user = Auth::user();
        $earnings = $user->investments()
            ->where('status', 'completed')
            ->with('loan')
            ->get();
        
        if ($earnings->isEmpty()) {
            return [
                'labels' => ['No Data'],
                'data' => [100],
            ];
        }
        
        // Group by loan purpose
        $distribution = $earnings->groupBy(function($item) {
            return $item->loan->purpose ?? 'Other';
        })->map(function($group) {
            return $group->sum('actual_return');
        });
        
        return [
            'labels' => $distribution->keys()->toArray(),
            'data' => $distribution->values()->toArray(),
        ];
    }
}
