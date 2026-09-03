<?php

namespace App\Http\Controllers\Lender;

use App\Http\Controllers\Controller;
use App\Modules\Funding\Models\Investment;
use App\Modules\Funding\Services\EarningsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EarningsController extends Controller
{
    public function __construct(protected EarningsService $earningsService)
    {
    }

    public function index()
    {
        $user = Auth::user();

        $earnings = $user->investments()
            ->with('loan')
            ->where('status', 'completed')
            ->latest('completed_at')
            ->paginate(20);

        $summary = $this->earningsService->getLenderEarningsSummary($user);

        $earningsData = $this->earningsService->getEarningsOverviewData($user, 'month');
        $earningsTypeData = $this->earningsService->getEarningsByTypeData($user);

        $earningsDataQuarter = $this->earningsService->getEarningsOverviewData($user, 'quarter');
        $earningsDataYear = $this->earningsService->getEarningsOverviewData($user, 'year');

        return view('client.earnings.index', compact(
            'earnings',
            'summary',
            'earningsData',
            'earningsTypeData',
            'earningsDataQuarter',
            'earningsDataYear'
        ));
    }

    public function downloadReceipt(Investment $investment)
    {
        $this->authorize('view', $investment);

        if (! $investment->isCompleted()) {
            return back()->with('error', 'Receipts are only available for completed investments.');
        }

        $investment->load('loan', 'lender');

        $currency = config('loan.general.currency_symbol', 'N$');
        $currencyCode = config('loan.general.currency', 'NAD');
        $roi = $this->earningsService->getInvestmentRoi($investment);
        $earnings = (float) $investment->actual_return - (float) $investment->amount;

        $pdf = Pdf::loadView('pdf.earnings-receipt', [
            'investment' => $investment,
            'currency' => $currency,
            'currencyCode' => $currencyCode,
            'roi' => $roi,
            'earnings' => $earnings,
            'generatedAt' => now(),
        ]);

        return response()->streamDownload(
            fn () => $pdf->output(),
            'earnings-receipt-' . $investment->id . '.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}
