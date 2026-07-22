<?php

namespace App\Http\Controllers\Borrower;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Services\LoanAgreementService;
use App\Modules\Loans\Services\LoanService;
use App\Modules\Loans\Services\TrustTierService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class LoanController extends Controller
{
    public function __construct(
        private LoanService $loanService,
        private TrustTierService $trustTierService,
        private LoanAgreementService $loanAgreementService,
    ) {}

    public function index()
    {
        $loans = Auth::user()->loans()->latest()->paginate(20);

        return view('client.loans.index', compact('loans'));
    }

    public function create()
    {
        $tier = $this->trustTierService->forScore((float) Auth::user()->trust_score);
        $minAmount = config('loans.min_amount');
        $maxAmount = $tier['maximum_loan'];
        $allowedDurations = $tier['allowed_durations'];
        $minTermDays = min($allowedDurations);
        $maxTermDays = max($allowedDurations);
        $platformFee = $tier['platform_fee_percent'];
        $lenderReturnPercent = $tier['lender_return_percent'];
        $interestRate = $platformFee + $lenderReturnPercent;
        $trustTier = $tier['name'];
        $maxActive = config('loans.max_active_loans');

        return view('client.loans.create', compact('minAmount', 'maxAmount', 'allowedDurations', 'minTermDays', 'maxTermDays', 'interestRate', 'platformFee', 'lenderReturnPercent', 'trustTier', 'maxActive'));
    }

    public function agreementPreview(Request $request)
    {
        $minimumAmount = (float) config('loans.min_amount');
        $tier = $this->trustTierService->forScore((float) $request->user()->trust_score);
        $validated = $request->validate([
            'amount' => ['required', 'numeric', "min:{$minimumAmount}", "max:{$tier['maximum_loan']}"],
            'repayment_period' => ['required', 'integer', Rule::in($tier['allowed_durations'])],
        ]);
        $calculation = $this->loanService->calculate(
            $request->user(),
            (float) $validated['amount'],
            (int) $validated['repayment_period'],
        );
        $pdf = $this->loanAgreementService->preview(
            $request->user(),
            $calculation,
            now()->addDays((int) $validated['repayment_period']),
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="loan-agreement-preview.pdf"',
        ]);
    }

    public function store(Request $request)
    {
        $kycSubmission = Auth::user()->kycSubmission;
        if (! $kycSubmission || ! $kycSubmission->isApproved()) {
            return redirect()->route('client.kyc.upload')->with('error', 'You must complete KYC verification before requesting a loan.');
        }

        $minimumAmount = (float) config('loans.min_amount');
        $tier = $this->trustTierService->forScore((float) Auth::user()->trust_score);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', "min:{$minimumAmount}", "max:{$tier['maximum_loan']}"],
            'purpose' => ['required', 'string', 'max:255'],
            'repayment_period' => ['required', 'integer', Rule::in($tier['allowed_durations'])],
            'description' => ['nullable', 'string', 'max:1000'],
            'agreement_read' => ['required', 'accepted'],
            'agreement_terms' => ['required', 'accepted'],
            'electronic_documents' => ['required', 'accepted'],
            'agreement_version' => ['required', 'string', Rule::in([(string) config('loan.agreement.version')])],
        ]);
        $validated['ip_address'] = $request->ip();
        $validated['user_agent'] = $request->userAgent();

        try {
            $loan = $this->loanService->createLoan(Auth::user(), $validated);
        } catch (ApiException $e) {
            return back()
                ->with('error', $e->getMessage())
                ->withInput();
        }

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
