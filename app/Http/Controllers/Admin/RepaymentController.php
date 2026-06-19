<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Repayments\Models\Repayment;
use Illuminate\Http\Request;

class RepaymentController extends Controller
{
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
            'total'     => Repayment::count(),
            'pending'   => Repayment::where('status', 'pending')->count(),
            'paid'      => Repayment::where('status', 'paid')->count(),
            'overdue'   => Repayment::where('status', 'overdue')->count(),
            'defaulted' => Repayment::where('status', 'defaulted')->count(),
        ];

        return view('admin.repayments.index', compact('repayments', 'stats'));
    }

    public function show(Repayment $repayment)
    {
        $repayment->load(['loan.borrower', 'loan.fundingTransactions.lender']);
        return view('admin.repayments.show', compact('repayment'));
    }

    public function confirm(Request $request, Repayment $repayment)
    {
        $repayment->update([
            'status'    => 'paid',
            'paid_date' => now()->toDateString(),
        ]);

        return redirect()->route('admin.repayments.show', $repayment)
            ->with('success', 'Repayment confirmed.');
    }
}
