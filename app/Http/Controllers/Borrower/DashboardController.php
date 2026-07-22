<?php

namespace App\Http\Controllers\Borrower;

use App\Http\Controllers\Controller;
use App\Modules\Loans\Services\LoanService;
use App\Modules\Repayments\Models\Repayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(protected LoanService $loanService)
    {
    }

    public function index()
    {
        $user = Auth::user();
        $loans = $user->loans()->latest()->take(5)->get();
        $repayments = $user->repayments()->latest()->take(5)->get();
        $trustScore = $user->trustScore;

        $activeLoans = $user->loans()->where('status', 'active')->get();
        $totalOutstanding = 0;
        foreach ($activeLoans as $loan) {
            $loanRepayments = Repayment::forLoan($loan->id)
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->get();

            foreach ($loanRepayments as $repayment) {
                $paidHistory = collect($repayment->metadata['payment_history'] ?? [])->sum('amount');
                $totalOutstanding += $this->loanService->outstandingBalance(
                    (float) $repayment->amount,
                    (float) $paidHistory,
                    (float) $repayment->penalty,
                );
            }
        }

        $upcomingRepayments = Repayment::forBorrower($user->id)
            ->upcoming(30)
            ->with('loan')
            ->orderBy('due_date')
            ->take(5)
            ->get();

        $repaymentChart = Repayment::forBorrower($user->id)
            ->selectRaw("DATE_FORMAT(due_date, '%Y-%m') as month, SUM(amount) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->take(6)
            ->get();

        $repaymentChartLabels = $repaymentChart->pluck('month')->map(function ($m) {
            return \Carbon\Carbon::createFromFormat('Y-m', $m)->format('M Y');
        })->toArray();
        $repaymentChartData = $repaymentChart->pluck('total')->map(fn ($v) => (float) $v)->toArray();

        return view('borrower.dashboard', compact(
            'user',
            'loans',
            'repayments',
            'trustScore',
            'totalOutstanding',
            'upcomingRepayments',
            'repaymentChartLabels',
            'repaymentChartData',
        ));
    }
}
