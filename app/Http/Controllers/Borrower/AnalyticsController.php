<?php

namespace App\Http\Controllers\Borrower;

use App\Http\Controllers\Controller;
use App\Modules\TrustScore\Services\TrustScoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AnalyticsController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $totalBorrowed = $user->loans()->sum('requested_amount');
        $totalRepaid = $user->repayments()->where('status', 'paid')->sum('amount');
        $activeLoansCount = $user->loans()->where('status', 'active')->count();
        $overdueRepayments = $user->repayments()->where('status', 'overdue')->count();

        $loansByStatus = $user->loans()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $repByStatus = $user->repayments()
            ->selectRaw('status, count(*) as total, sum(amount) as total_amount')
            ->groupBy('status')
            ->get();

        $score = (float) $user->trust_score;
        $tier = TrustScoreService::getTier($score);

        return view('borrower.analytics', compact(
            'user',
            'totalBorrowed',
            'totalRepaid',
            'activeLoansCount',
            'overdueRepayments',
            'loansByStatus',
            'repByStatus',
            'score',
            'tier',
        ));
    }
}
