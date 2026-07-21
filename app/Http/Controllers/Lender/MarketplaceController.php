<?php

namespace App\Http\Controllers\Lender;

use App\Http\Controllers\Controller;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Services\LoanService;
use App\Modules\Funding\Services\FundingService;
use App\Modules\Marketplace\Services\MarketplaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MarketplaceController extends Controller
{
    public function __construct(
        private FundingService $fundingService,
        private MarketplaceService $marketplaceService,
        private LoanService $loanService,
    ) {}

    public function index()
    {
        $loans = Loan::whereIn('status', ['marketplace', 'partially_funded'])
            ->where('borrower_id', '!=', Auth::id())
            ->with('borrower:id,first_name,last_name,trust_score')
            ->latest('approved_at')
            ->paginate(12);

        $loans->getCollection()->transform(function ($loan) {
            $loan->display = $this->displayFor($loan);

            return $loan;
        });

        return view('client.marketplace.index', compact('loans'));
    }

    public function show(Loan $loan): JsonResponse
    {
        if (! $loan->isOnMarketplace()) {
            abort(404, 'Listing not found or no longer on marketplace.');
        }

        $listing = $this->marketplaceService->getListing($loan->id);

        if (! $listing) {
            abort(404, 'Listing not found or no longer on marketplace.');
        }

        return response()->json($listing);
    }

    public function fund(Request $request, Loan $loan)
    {
        $remaining = $this->loanService->remainingFunding($loan);
        $validated = $request->validate([
            'amount' => 'required|numeric|min:' . config('loans.min_funding_amount', 500) . '|max:' . $remaining,
        ]);

        $transaction = $this->fundingService->fund(Auth::user(), $loan, $validated['amount']);

        return redirect()->route('client.funding.payment', $transaction)
            ->with('success', 'Funding pledge created. Please complete your payment.');
    }

    protected function displayFor(Loan $loan): array
    {
        $listing = $this->marketplaceService->transformListing($loan);
        $riskLevel = $listing['borrower']['risk_level'];

        return [
            'loan_amount' => $listing['loan']['approved_amount'],
            'total_loan_charge' => $listing['loan']['total_loan_charge'],
            'platform_fee' => $listing['loan']['platform_fee'],
            'lender_return' => $listing['loan']['lender_return'],
            'expected_return' => $listing['loan']['expected_return'],
            'expected_profit' => $listing['loan']['expected_profit'],
            'borrower_repayment' => $listing['loan']['borrower_repayment'],
            'funded_amount' => $listing['funding']['funded_amount'],
            'remaining_amount' => $listing['funding']['remaining_amount'],
            'progress_percent' => $listing['funding']['progress_percent'],
            'risk_level' => $riskLevel,
            'risk_color' => $riskLevel === 'low' ? 'success' : ($riskLevel === 'medium' ? 'warning' : 'danger'),
            'trust_score' => $listing['borrower']['trust_score'],
        ];
    }
}
