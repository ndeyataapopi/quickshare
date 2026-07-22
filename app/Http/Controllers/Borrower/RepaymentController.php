<?php

namespace App\Http\Controllers\Borrower;

use App\Http\Controllers\Controller;
use App\Modules\Loans\Models\Loan;
use App\Modules\Repayments\Models\Repayment;
use App\Modules\Repayments\Services\RepaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RepaymentController extends Controller
{
    public function __construct(protected RepaymentService $repaymentService)
    {
    }

    public function index()
    {
        $repayments = Auth::user()->repayments()->with('loan')->latest()->paginate(20);
        $upcoming   = Auth::user()->repayments()->where('status', 'pending')->orderBy('due_date')->take(5)->get();
        return view('client.repayments.index', compact('repayments', 'upcoming'));
    }

    public function show(Repayment $repayment)
    {
        $this->authorize('view', $repayment);
        return view('client.repayments.show', compact('repayment'));
    }

    public function create(Request $request)
    {
        $user = Auth::user();

        $query = $user->repayments()
            ->with('loan')
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->orderBy('due_date');

        if ($request->filled('loan_id')) {
            $query->where('loan_id', $request->integer('loan_id'));
        }

        $eligibleRepayments = $query->get();

        $loans = Loan::forBorrower($user->id)
            ->active()
            ->orderBy('created_at', 'desc')
            ->get();

        return view('client.repayments.create', compact('eligibleRepayments', 'loans'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'repayment_ids' => ['required', 'array', 'min:1'],
            'repayment_ids.*' => ['required', 'integer', 'exists:repayments,id'],
            'payment_method' => ['required', 'string', Rule::in(['eft', 'mobile_wallet', 'cash_deposit'])],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'proof_of_payment' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $user = Auth::user();

        $repayments = Repayment::whereIn('id', $validated['repayment_ids'])
            ->where('borrower_id', $user->id)
            ->get();

        if ($repayments->isEmpty()) {
            return back()->withErrors(['repayment_ids' => 'No eligible repayments found.'])->withInput();
        }

        foreach ($repayments as $repayment) {
            if (! $repayment->loan->isActive()) {
                return back()->withErrors(['repayment_ids' => "Loan #{$repayment->loan_id} is not active."])->withInput();
            }
        }

        try {
            $proofPath = $request->file('proof_of_payment')->store('repayment-proofs', 'public');

            $this->repaymentService->submitRepaymentRequest(
                $validated['repayment_ids'],
                $user,
                $validated['payment_method'],
                $proofPath,
                $validated['external_reference'] ?? null,
            );

            return redirect()->route('client.repayments.index')
                ->with('success', 'Repayment request submitted for approval. You will be notified once it is reviewed.');
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }
}
