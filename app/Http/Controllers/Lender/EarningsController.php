<?php

namespace App\Http\Controllers\Lender;

use App\Http\Controllers\Controller;
use App\Modules\Funding\Services\EarningsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EarningsController extends Controller
{
    public function __construct(protected EarningsService $earningsService)
    {
    }

    public function index()
    {
        $user = Auth::user();

        $earnings = $user->investments()
            ->with('loan')
            ->where('status', 'completed')
            ->latest('completed_at')
            ->paginate(20);

        $summary = $this->earningsService->getLenderEarningsSummary($user);

        $earningsData = $this->earningsService->getEarningsOverviewData($user, 'month');
        $earningsTypeData = $this->earningsService->getEarningsByTypeData($user);

        $earningsDataQuarter = $this->earningsService->getEarningsOverviewData($user, 'quarter');
        $earningsDataYear = $this->earningsService->getEarningsOverviewData($user, 'year');

        return view('client.earnings.index', compact(
            'earnings',
            'summary',
            'earningsData',
            'earningsTypeData',
            'earningsDataQuarter',
            'earningsDataYear'
        ));
    }
}
