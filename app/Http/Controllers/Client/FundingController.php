<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Modules\Funding\Models\FundingTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FundingController extends Controller
{
    public function show(FundingTransaction $transaction)
    {
        $this->authorizeTransaction($transaction);
        $transaction->load(['loan.borrower']);
        return view('client.funding.show', compact('transaction'));
    }

    public function payment(FundingTransaction $transaction)
    {
        $this->authorizeTransaction($transaction);
        $transaction->load(['loan.borrower']);
        return view('client.funding.payment', compact('transaction'));
    }

    public function submitPayment(Request $request, FundingTransaction $transaction)
    {
        $this->authorizeTransaction($transaction);

        $request->validate([
            'payment_reference' => 'required|string|max:255',
            'payment_method'    => 'required|in:eft,instant_eft,bank_transfer',
        ]);

        $transaction->update([
            'notes'    => 'Payment reference: ' . $request->payment_reference . ' | Method: ' . $request->payment_method,
        ]);

        return redirect()->route('client.investments.index')
            ->with('success', 'Payment details submitted. Your investment will be confirmed once payment is verified.');
    }

    private function authorizeTransaction(FundingTransaction $transaction): void
    {
        if ($transaction->lender_id !== Auth::id()) {
            abort(403, 'Unauthorized.');
        }
    }
}
