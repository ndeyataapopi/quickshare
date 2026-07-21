<?php

namespace App\Http\Controllers\Lender;

use App\Http\Controllers\Controller;
use App\Modules\Funding\Services\EarningsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(protected EarningsService $earningsService)
    {
    }

    public function index()
    {
        $user = Auth::user();

        $investments = $user->investments()
            ->whereHas('loan', fn($q) => $q->where('status', 'active'))
            ->latest()
            ->take(5)
            ->get();

        $earnings = $user->investments()
            ->where('status', 'completed')
            ->latest('completed_at')
            ->take(5)
            ->get();

        $summary = $this->earningsService->getLenderEarningsSummary($user);

        return view('lender.dashboard', compact('user', 'investments', 'earnings', 'summary'));
    }
}
