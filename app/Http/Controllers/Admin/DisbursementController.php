<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Services\DisbursementService;
use Illuminate\Http\Request;

class DisbursementController extends Controller
{
    public function __construct(private DisbursementService $disbursementService) {}

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

        try {
            $transaction = $this->disbursementService->initiateDisbursement($loan);

            return redirect()->route('admin.disbursements.show', $loan)
                ->with('success', "Disbursement initiated: {$transaction->transaction_reference}. Confirm once funds are sent to borrower.");
        } catch (ApiException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function confirm(Request $request, Loan $loan)
    {
        $transaction = $loan->disbursements()
            ->pendingProcessing()
            ->latest()
            ->first();

        if (! $transaction) {
            return back()->with('error', 'No awaiting disbursement transaction found for this loan.');
        }

        try {
            $this->disbursementService->processDisbursement($transaction);

            return redirect()->route('admin.disbursements.show', $loan)
                ->with('success', 'Disbursement confirmed and loan is now active.');
        } catch (ApiException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
