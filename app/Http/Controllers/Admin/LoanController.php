<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Loans\Mail\LoanAgreementMail;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Services\LoanService;
use App\Modules\Loans\Services\TrustTierService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class LoanController extends Controller
{
    public function __construct(
        private LoanService $loanService,
        private TrustTierService $trustTierService,
    ) {}

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

        $stats = [
            'total'          => Loan::count(),
            'pending_review' => Loan::where('status', 'pending_review')->count(),
            'active'         => Loan::where('status', 'active')->count(),
            'defaulted'      => Loan::where('status', 'defaulted')->count(),
            'total_disbursed'=> Loan::whereIn('status', ['active','completed','defaulted'])->sum('approved_amount'),
        ];

        $loans = $query->paginate(20)->withQueryString();
        return view('admin.loans.index', compact('loans', 'stats'));
    }

    public function show(Loan $loan)
    {
        return view('admin.loans.show', compact('loan'));
    }

    public function update(Request $request, Loan $loan)
    {
        $validated = $request->validate([
            'decision'        => 'required|in:approve,reject',
            'approved_amount' => 'nullable|numeric|min:1',
            'notes'           => 'nullable|string|max:1000',
        ]);

        $reviewer = $request->user();

        if ($validated['decision'] === 'approve') {
            $this->loanService->approve(
                $loan,
                $reviewer,
                isset($validated['approved_amount']) ? (float) $validated['approved_amount'] : null,
                $validated['notes'] ?? null
            );
        } else {
            $this->loanService->reject(
                $loan,
                $reviewer,
                $validated['notes'] ?? 'Rejected by admin.'
            );
        }

        return redirect()->route('admin.loans.index')->with('success', 'Loan review completed.');
    }

    public function agreement(Loan $loan)
    {
        $loan->load('borrower');

        $trustScore = (float) ($loan->risk_score ?? 0);
        $tier = $loan->configuration_snapshot['trust_tier']['name']
            ?? $this->trustTierService->forScore($trustScore)['name']
            ?? 'unknown';

        return view('admin.loans.agreement', compact('loan', 'trustScore', 'tier'));
    }

    public function pdf(Loan $loan)
    {
        return $this->serveAgreement($loan, 'inline');
    }

    public function download(Loan $loan)
    {
        return $this->serveAgreement($loan, 'attachment');
    }

    protected function serveAgreement(Loan $loan, string $disposition)
    {
        $path = $loan->agreement_path;
        $disk = (string) config('loan.agreement.disk');

        if (! $path || ! Storage::disk($disk)->exists($path)) {
            abort(404, 'Loan agreement PDF not found.');
        }

        $filename = "loan-agreement-{$loan->reference}.pdf";

        return response(Storage::disk($disk)->get($path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
        ]);
    }

    public function resend(Request $request, Loan $loan)
    {
        $loan->load('borrower');

        if (! $loan->agreement_path) {
            return redirect()->back()->with('error', 'No agreement has been generated for this loan.');
        }

        $disk = (string) config('loan.agreement.disk');
        if (! \Storage::disk($disk)->exists($loan->agreement_path)) {
            return redirect()->back()->with('error', 'Agreement file not found on storage disk.');
        }

        Mail::to($loan->borrower->email)->queue(new LoanAgreementMail($loan));

        return redirect()->back()->with('success', 'Loan agreement email resent to '.$loan->borrower->email);
    }
}
