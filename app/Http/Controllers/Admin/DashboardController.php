<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Modules\Loans\Models\Loan;
use App\Modules\Admin\Services\AdminDashboardService;
use App\Modules\Funding\Services\EarningsService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected AdminDashboardService $dashboardService,
        protected EarningsService $earningsService
    ) {
    }

    public function index()
    {
        $stats = $this->dashboardService->getOverviewStats();

        $earningsSummary = $this->earningsService->getPlatformEarningsSummary();
        $revenueStats = $this->dashboardService->getRevenueStats();
        $chartData = $this->dashboardService->getChartData();

        $recentLoans = Loan::with('borrower:id,first_name,last_name')->latest()->take(10)->get();
        $recentActivity = ActivityLog::with('user:id,first_name,last_name')
            ->latest()
            ->take(10)
            ->get();

        return view('admin.dashboard', compact('stats', 'earningsSummary', 'revenueStats', 'chartData', 'recentLoans', 'recentActivity'));
    }
}
