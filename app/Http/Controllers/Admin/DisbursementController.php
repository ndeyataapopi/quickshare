<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Services\LoanService;
use Illuminate\Http\Request;

class DisbursementController extends Controller
{
    public function __construct(private LoanService $loanService) {}

    public function index(Request $request)
    {
        $query = Loan::with('borrower')->latest();

        if ($search = $request->input('search')) {
            $query->where('reference', 'like', "%{$search}%")
                  ->orWhereHas('borrower', fn($u) => $u->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%"));
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $loans = $query->paginate(20)->withQueryString();

        $stats = [
            'total'           => Loan::count(),
            'funded'          => Loan::where('status', 'funded')->count(),
            'disbursed'       => Loan::where('status', 'disbursed')->count(),
            'active'          => Loan::where('status', 'active')->count(),
            'total_disbursed' => Loan::whereIn('status', ['disbursed', 'active'])->sum('approved_amount'),
        ];

        return view('admin.disbursements.index', compact('loans', 'stats'));
    }

    public function show(Loan $loan)
    {
        $loan->load(['borrower', 'fundingTransactions.lender', 'disbursements']);
        return view('admin.disbursements.show', compact('loan'));
    }

    public function disburse(Request $request, Loan $loan)
    {
        if (! $loan->isDisbursable()) {
            return back()->with('error', 'This loan cannot be disbursed in its current state.');
        }

        $loan->update([
            'status'       => 'disbursed',
            'disbursed_at' => now(),
        ]);

        return redirect()->route('admin.disbursements.show', $loan)
            ->with('success', 'Loan marked as disbursed. Confirm once funds are sent to borrower.');
    }

    public function confirm(Request $request, Loan $loan)
    {
        if ($loan->status !== 'disbursed') {
            return back()->with('error', 'Loan must be in disbursed state to confirm.');
        }

        $loan->update(['status' => 'active']);

        return redirect()->route('admin.disbursements.show', $loan)
            ->with('success', 'Disbursement confirmed. Loan is now active.');
    }
}
