<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Repayments\Models\Repayment;
use App\Modules\Repayments\Services\RepaymentService;
use Illuminate\Http\Request;

class RepaymentController extends Controller
{
    public function __construct(protected RepaymentService $repaymentService)
    {
    }

    public function index(Request $request)
    {
        $query = Repayment::with(['loan.borrower'])->latest();

        if ($search = $request->input('search')) {
            $query->whereHas('loan', function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhereHas('borrower', fn($u) => $u->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($from = $request->input('from')) {
            $query->whereDate('due_date', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->whereDate('due_date', '<=', $to);
        }

        $repayments = $query->paginate(20)->withQueryString();

        $stats = [
            'total'             => Repayment::count(),
            'pending'           => Repayment::where('status', 'pending')->count(),
            'pending_approval'  => Repayment::where('status', 'pending_approval')->count(),
            'completed'         => Repayment::where('status', 'paid')->count(),
            'overdue'           => Repayment::where('status', 'overdue')->count(),
            'defaulted'         => Repayment::where('status', 'defaulted')->count(),
            'rejected'          => Repayment::where('status', 'rejected')->count(),
        ];

        return view('admin.repayments.index', compact('repayments', 'stats'));
    }

    public function show(Repayment $repayment)
    {
        $repayment->load(['loan.borrower', 'loan.fundingTransactions.lender']);
        return view('admin.repayments.show', compact('repayment'));
    }

    public function approve(Request $request, Repayment $repayment)
    {
        try {
            $this->repaymentService->approveRepayment($repayment, $request->user());

            return redirect()->route('admin.repayments.show', $repayment)
                ->with('success', 'Repayment approved and processed.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.repayments.show', $repayment)
                ->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, Repayment $repayment)
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->repaymentService->rejectRepayment($repayment, $request->user(), $validated['reason'] ?? null);

            return redirect()->route('admin.repayments.show', $repayment)
                ->with('success', 'Repayment rejected. Borrower has been notified.');
        } catch (\Throwable $e) {
            return redirect()->route('admin.repayments.show', $repayment)
                ->with('error', $e->getMessage());
        }
    }
}
