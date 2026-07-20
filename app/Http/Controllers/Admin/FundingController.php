<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\Funding\Services\FundingService;
use Illuminate\Http\Request;

class FundingController extends Controller
{
    public function __construct(private FundingService $fundingService) {}

    public function index(Request $request)
    {
        $query = FundingTransaction::with(['loan.borrower', 'lender'])->latest();

        if ($search = $request->input('search')) {
            $query->where('transaction_reference', 'like', "%{$search}%")
                  ->orWhereHas('lender', fn($u) => $u->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%"))
                  ->orWhereHas('loan', fn($l) => $l->where('reference', 'like', "%{$search}%"));
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $transactions = $query->paginate(20)->withQueryString();

        $stats = [
            'total'            => FundingTransaction::count(),
            'pending'          => FundingTransaction::where('status', 'pending')->count(),
            'confirmed'        => FundingTransaction::where('status', 'confirmed')->count(),
            'cancelled'        => FundingTransaction::where('status', 'cancelled')->count(),
            'total_confirmed'  => FundingTransaction::where('status', 'confirmed')->sum('amount'),
        ];

        return view('admin.funding.index', compact('transactions', 'stats'));
    }

    public function show(FundingTransaction $transaction)
    {
        $transaction->load(['loan.borrower', 'lender']);
        return view('admin.funding.show', compact('transaction'));
    }

    public function confirm(Request $request, FundingTransaction $transaction)
    {
        $this->fundingService->confirmFunding($transaction, $request->user(), $request->input('admin_notes'));
        return redirect()->route('admin.funding.show', $transaction)
            ->with('success', 'Funding transaction confirmed and applied to the loan.');
    }

    public function reject(Request $request, FundingTransaction $transaction)
    {
        $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $this->fundingService->rejectFunding($transaction, $request->user(), $request->input('reason'));
        return redirect()->route('admin.funding.show', $transaction)
            ->with('success', 'Funding transaction rejected and the lender has been notified.');
    }

    public function requestInfo(Request $request, FundingTransaction $transaction)
    {
        $request->validate(['message' => ['required', 'string', 'max:2000']]);
        $this->fundingService->requestFundingInfo($transaction, $request->user(), $request->input('message'));
        return redirect()->route('admin.funding.show', $transaction)
            ->with('success', 'A request for more information has been sent to the lender.');
    }

    public function cancel(Request $request, FundingTransaction $transaction)
    {
        $this->fundingService->cancelFunding($transaction);
        return redirect()->route('admin.funding.index')
            ->with('success', 'Funding transaction cancelled.');
    }
}
