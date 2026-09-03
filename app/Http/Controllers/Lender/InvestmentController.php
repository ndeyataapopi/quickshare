<?php

namespace App\Http\Controllers\Lender;

use App\Http\Controllers\Controller;
use App\Modules\Funding\Models\Investment;
use App\Modules\Funding\Services\EarningsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvestmentController extends Controller
{
    public function __construct(protected EarningsService $earningsService)
    {
    }

    public function index()
    {
        $user = Auth::user();

        $investments = $user->investments()
            ->with('loan.borrower')
            ->latest()
            ->paginate(20);

        $earningsSummary = $this->earningsService->getLenderEarningsSummary($user);

        $portfolioData = $this->earningsService->getPortfolioPerformanceData($user);
        $distributionData = $this->earningsService->getInvestmentDistributionData($user);

        return view('client.investments.index', compact('investments', 'earningsSummary', 'portfolioData', 'distributionData'));
    }

    public function show(Investment $investment)
    {
        $this->authorize('view', $investment);
        return view('client.investments.show', compact('investment'));
    }

    public function downloadStatement(Investment $investment)
    {
        $this->authorize('view', $investment);

        $investment->load('loan', 'lender');

        $currency = config('loan.general.currency_symbol', 'N$');
        $currencyCode = config('loan.general.currency', 'NAD');

        $pdf = Pdf::loadView('pdf.investment-statement', [
            'investment' => $investment,
            'currency' => $currency,
            'currencyCode' => $currencyCode,
            'generatedAt' => now(),
        ]);

        return response()->streamDownload(
            fn () => $pdf->output(),
            'investment-statement-' . $investment->id . '.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}
