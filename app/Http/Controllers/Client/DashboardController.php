<?php

namespace App\Http\Controllers\Client;

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

        // Get user's loans (as borrower)
        $loans = $user->loans()->latest()->take(5)->get();

        // Get user's investments (as lender)
        $investments = $user->investments()->whereHas('loan', fn($q) => $q->where('status', 'active'))->latest()->take(5)->get();

        // Get user's repayments
        $repayments = $user->repayments()->latest()->take(5)->get();

        $earnings = $user->investments()->where('status', 'completed')->latest('completed_at')->take(5)->get();

        // Get trust score
        $trustScore = $user->trustScore;

        // Get real totals from database via EarningsService
        $earningsSummary = $this->earningsService->getLenderEarningsSummary($user);
        $totalInvested = $earningsSummary['total_invested'];
        $totalEarnings = $earningsSummary['total_earnings'];
        $activeInvestmentsCount = $earningsSummary['active_count'];
        $totalLoansAmount = $user->loans()->sum('approved_amount');
        $activeLoansCount = $user->loans()->whereIn('status', ['active', 'disbursed'])->count();

        return view('client.dashboard', compact(
            'user', 'loans', 'investments', 'repayments', 'earnings', 'trustScore',
            'earningsSummary', 'totalInvested', 'totalEarnings', 'activeInvestmentsCount',
            'totalLoansAmount', 'activeLoansCount'
        ));
    }

    // //lender
    // public function index()
    // {
    //     // $user = Auth::user();
    //     $investments = $user->investments()->whereHas('loan', fn($q) => $q->where('status', 'active'))->latest()->take(5)->get();
    //     $earnings = $user->investments()->latest()->take(5)->get();
        
    //     return view('lender.dashboard', compact('user', 'investments', 'earnings'));
    // }

    // //borrower
    // public function index()
    // {
    //     // $user = Auth::user();
    //     $loans = $user->loans()->latest()->take(5)->get();
    //     $repayments = $user->repayments()->latest()->take(5)->get();
    //     $trustScore = $user->trustScore;
        
    //     return view('borrower.dashboard', compact('user', 'loans', 'repayments', 'trustScore'));
    // }
}
