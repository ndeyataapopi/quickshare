<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\AdminDashboardService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    use ApiResponse;

    public function __construct(protected AdminDashboardService $dashboardService)
    {
    }

    // ─── Main Dashboard ─────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $metrics = $this->dashboardService->getDashboardMetrics();

        return $this->success($metrics, 'Dashboard metrics retrieved.');
    }

    // ─── Overview Stats ──────────────────────────────────────────────

    public function overview(Request $request): JsonResponse
    {
        $stats = $this->dashboardService->getOverviewStats();

        return $this->success($stats, 'Overview stats retrieved.');
    }

    // ─── KYC Stats ─────────────────────────────────────────────────────

    public function kycStats(Request $request): JsonResponse
    {
        $stats = $this->dashboardService->getKycStats();

        return $this->success($stats, 'KYC stats retrieved.');
    }

    // ─── Loan Stats ────────────────────────────────────────────────────

    public function loanStats(Request $request): JsonResponse
    {
        $stats = $this->dashboardService->getLoanStats();

        return $this->success($stats, 'Loan stats retrieved.');
    }

    // ─── Funding Stats ───────────────────────────────────────────────

    public function fundingStats(Request $request): JsonResponse
    {
        $stats = $this->dashboardService->getFundingStats();

        return $this->success($stats, 'Funding stats retrieved.');
    }

    // ─── Repayment Stats ─────────────────────────────────────────────

    public function repaymentStats(Request $request): JsonResponse
    {
        $stats = $this->dashboardService->getRepaymentStats();

        return $this->success($stats, 'Repayment stats retrieved.');
    }

    // ─── Collections Stats ─────────────────────────────────────────────

    public function collectionsStats(Request $request): JsonResponse
    {
        $stats = $this->dashboardService->getCollectionsStats();

        return $this->success($stats, 'Collections stats retrieved.');
    }

    // ─── User Stats ────────────────────────────────────────────────────

    public function userStats(Request $request): JsonResponse
    {
        $stats = $this->dashboardService->getUserStats();

        return $this->success($stats, 'User stats retrieved.');
    }

    // ─── Revenue Stats ─────────────────────────────────────────────────

    public function revenueStats(Request $request): JsonResponse
    {
        $stats = $this->dashboardService->getRevenueStats();

        return $this->success($stats, 'Revenue stats retrieved.');
    }

    // ─── Chart Data ────────────────────────────────────────────────────

    public function charts(Request $request): JsonResponse
    {
        $charts = $this->dashboardService->getChartData();

        return $this->success($charts, 'Chart data retrieved.');
    }

    // ─── Recent Activity ─────────────────────────────────────────────

    public function recentActivity(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 10);
        $activity = $this->dashboardService->getRecentActivity($limit);

        return $this->success($activity, 'Recent activity retrieved.');
    }
}
