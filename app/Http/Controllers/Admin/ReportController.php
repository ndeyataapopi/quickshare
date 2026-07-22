<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Services\LoanFinancialSummaryService;
use App\Modules\Repayments\Models\Repayment;
use App\Modules\Funding\Models\FundingTransaction;
use App\Models\User;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private LoanFinancialSummaryService $summaryService,
    ) {}

    public function index(Request $request)
    {
        $period = $request->input('period', 'month');
        $dateFrom = $this->getDateFrom($period);
        $dateTo = now();

        $stats = [
            'total_loans' => Loan::whereBetween('created_at', [$dateFrom, $dateTo])->count(),
            'total_loan_amount' => Loan::whereBetween('created_at', [$dateFrom, $dateTo])->sum('requested_amount'),
            'active_loans' => Loan::where('status', 'active')->whereBetween('created_at', [$dateFrom, $dateTo])->count(),
            'completed_loans' => Loan::where('status', 'completed')->whereBetween('created_at', [$dateFrom, $dateTo])->count(),
            'total_repayments' => Repayment::whereBetween('created_at', [$dateFrom, $dateTo])->sum('amount'),
            'total_funding' => FundingTransaction::where('status', 'confirmed')->whereBetween('created_at', [$dateFrom, $dateTo])->sum('amount'),
            'new_users' => User::whereBetween('created_at', [$dateFrom, $dateTo])->count(),
            'verified_users' => User::whereHas('kycSubmission', fn($q) => $q->where('status', 'approved'))->whereBetween('created_at', [$dateFrom, $dateTo])->count(),
        ];

        return view('admin.reports.index', compact('stats', 'period'));
    }

    public function show(Request $request, $type)
    {
        $period = $request->input('period', 'month');
        $dateFrom = $this->getDateFrom($period);
        $dateTo = now();

        $data = match($type) {
            'loans' => $this->getLoansReport($dateFrom, $dateTo),
            'repayments' => $this->getRepaymentsReport($dateFrom, $dateTo),
            'funding' => $this->getFundingReport($dateFrom, $dateTo),
            'users' => $this->getUsersReport($dateFrom, $dateTo),
            'reconciliation' => $this->getReconciliationReport($dateFrom, $dateTo),
            default => [],
        };

        return view('admin.reports.show', compact('type', 'data', 'period'));
    }

    private function getDateFrom($period)
    {
        return match($period) {
            'today' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'quarter' => now()->startOfQuarter(),
            'year' => now()->startOfYear(),
            default => now()->subMonth(),
        };
    }

    private function getLoansReport($dateFrom, $dateTo)
    {
        return Loan::with(['borrower'])
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->latest()
            ->paginate(20);
    }

    private function getRepaymentsReport($dateFrom, $dateTo)
    {
        return Repayment::with(['loan.borrower'])
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->latest()
            ->paginate(20);
    }

    private function getFundingReport($dateFrom, $dateTo)
    {
        return FundingTransaction::with(['loan.borrower', 'lender'])
            ->where('status', 'confirmed')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->latest()
            ->paginate(20);
    }

    private function getUsersReport($dateFrom, $dateTo)
    {
        return User::with(['kycSubmission'])
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->latest()
            ->paginate(20);
    }

    private function getReconciliationReport($dateFrom, $dateTo)
    {
        $loans = Loan::whereIn('status', ['active', 'completed', 'defaulted'])
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->orderBy('created_at', 'desc')
            ->get();

        return $loans->map(function (Loan $loan) {
            $summary = $this->summaryService->generate($loan);
            $recon = $summary['reconciliation'];

            return [
                'loan_id' => $loan->id,
                'reference' => $loan->reference,
                'borrower' => $loan->borrower?->first_name . ' ' . $loan->borrower?->last_name,
                'status' => $loan->status,
                'money_in' => $recon['money_in'],
                'money_out' => $recon['money_out'],
                'platform_revenue' => $recon['platform_revenue'],
                'reconciled' => $recon['reconciled'],
                'discrepancies' => count($recon['discrepancies']),
            ];
        });
    }
}
