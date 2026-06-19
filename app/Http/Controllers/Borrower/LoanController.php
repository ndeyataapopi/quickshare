<?php

namespace App\Http\Controllers\Borrower;

use App\Http\Controllers\Controller;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Services\LoanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoanController extends Controller
{
    public function __construct(
        private LoanService $loanService
    ) {}

    public function index()
    {
        $loans = Auth::user()->loans()->latest()->paginate(20);
        return view('client.loans.index', compact('loans'));
    }

    public function create()
    {
        $minAmount   = config('loans.min_amount');
        $maxAmount   = config('loans.max_amount');
        $minTermDays = config('loans.min_term_days');
        $maxTermDays = config('loans.max_term_days');
        $interestRate = config('loans.interest_rate');
        $platformFee  = config('loans.platform_fee_percent');
        $maxActive    = config('loans.max_active_loans');
        return view('client.loans.create', compact('minAmount', 'maxAmount', 'minTermDays', 'maxTermDays', 'interestRate', 'platformFee', 'maxActive'));
    }

    public function store(Request $request)
    {
        $kycSubmission = Auth::user()->kycSubmission;
        if (!$kycSubmission || !$kycSubmission->isApproved()) {
            return redirect()->route('client.kyc.upload')->with('error', 'You must complete KYC verification before requesting a loan.');
        }

        $minAmount   = (int) config('loans.min_amount');
        $maxAmount   = (int) config('loans.max_amount');
        $minTermDays = (int) config('loans.min_term_days');
        $maxTermDays = (int) config('loans.max_term_days');

        $validated = $request->validate([
            'amount'           => "required|numeric|min:{$minAmount}|max:{$maxAmount}",
            'purpose'          => 'required|string|max:255',
            'repayment_period' => "required|integer|min:{$minTermDays}|max:{$maxTermDays}",
            'description'      => 'nullable|string|max:1000',
        ]);

        $loan = $this->loanService->createLoan(Auth::user(), $validated);

        return redirect()->route('client.loans.show', $loan)->with('success', 'Loan request submitted successfully.');
    }

    public function show(Loan $loan)
    {
        $this->authorize('view', $loan);
        return view('client.loans.show', compact('loan'));
    }

    public function cancel(Loan $loan)
    {
        $this->authorize('view', $loan);
        $this->loanService->cancel($loan, Auth::user());
        return redirect()->route('client.loans.index')->with('success', 'Loan cancelled.');
    }
}
