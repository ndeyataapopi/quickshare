<?php

namespace App\Http\Controllers\Lender;

use App\Http\Controllers\Controller;
use App\Modules\Loans\Models\Loan;
use App\Modules\Funding\Services\FundingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MarketplaceController extends Controller
{
    public function __construct(
        private FundingService $fundingService
    ) {}

    public function index()
    {
        $loans = Loan::whereIn('status', ['marketplace', 'partially_funded'])
            ->where('borrower_id', '!=', Auth::id())
            ->with('borrower:id,first_name,last_name,trust_score')
            ->latest('approved_at')
            ->paginate(12);
        return view('client.marketplace.index', compact('loans'));
    }

    public function fund(Request $request, Loan $loan)
    {
        $remaining = max(0, (float)($loan->approved_amount ?? $loan->requested_amount) - (float)$loan->funded_amount);
        $validated = $request->validate([
            'amount' => 'required|numeric|min:' . config('loans.min_funding_amount', 500) . '|max:' . $remaining,
        ]);

        $transaction = $this->fundingService->fund(Auth::user(), $loan, $validated['amount']);

        return redirect()->route('client.funding.payment', $transaction)
            ->with('success', 'Funding pledge created. Please complete your payment.');
    }
}
