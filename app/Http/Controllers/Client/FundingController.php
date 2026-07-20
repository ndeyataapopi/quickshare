<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Funding\SubmitPaymentRequest;
use App\Modules\Funding\Models\FundingTransaction;
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

        $transaction->payment_reference ??= 'QS-LOAN-' . $transaction->loan_id . '-' . $transaction->id;
        $transaction->save();

        return view('client.funding.payment', compact('transaction'));
    }

    public function submitPayment(SubmitPaymentRequest $request, FundingTransaction $transaction)
    {
        $this->authorizeTransaction($transaction);

        if (! $transaction->isPending()) {
            return redirect()->route('client.funding.show', $transaction)
                ->with('error', 'This funding transaction cannot be updated.');
        }

        $validated = $request->validated();

        $proofPath = $request->file('proof_of_payment')->store('funding-payments', 'public');

        $metadata = $transaction->metadata ?? [];
        $metadata['payer_reference_number'] = $validated['reference_number'] ?? null;
        $metadata['payer_transaction_number'] = $validated['transaction_number'] ?? null;

        $transaction->update([
            'payment_method' => $validated['payment_method'],
            'payment_method_detail' => $validated['payment_method_detail'] ?? null,
            'payment_reference' => $validated['payment_reference'] ?? $transaction->payment_reference,
            'payment_proof_path' => $proofPath,
            'payment_date' => $validated['payment_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'metadata' => $metadata,
        ]);

        // Notify admins that a funding payment is awaiting verification
        app(\App\Modules\Notifications\Services\NotificationService::class)->queue(
            $this->adminUser(),
            'funding_payment_submitted',
            [
                'loan_id' => $transaction->loan_id,
                'reference' => $transaction->loan->reference,
                'amount' => (float) $transaction->amount,
                'transaction_id' => $transaction->id,
                'lender_id' => $transaction->lender_id,
            ]
        );

        return redirect()->route('client.investments.index')
            ->with('success', 'Payment details submitted. Your investment will be confirmed once the admin verifies your payment.');
    }

    private function authorizeTransaction(FundingTransaction $transaction): void
    {
        if ($transaction->lender_id !== Auth::id()) {
            abort(403, 'Unauthorized.');
        }
    }

    private function adminUser(): \App\Models\User
    {
        return \App\Models\User::role('admin')->first()
            ?? \App\Models\User::firstOrFail();
    }
}
