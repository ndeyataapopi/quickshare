<?php

namespace App\Http\Controllers\Lender;

use App\Http\Controllers\Controller;
use App\Modules\Funding\Models\Investment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvestmentController extends Controller
{
    public function index()
    {
        $investments = Auth::user()->investments()
            ->with('loan.borrower')
            ->latest()
            ->paginate(20);
        $summary = [
            'total_invested'   => Auth::user()->investments()->whereIn('status', ['active','completed'])->sum('amount'),
            'total_expected'   => Auth::user()->investments()->whereIn('status', ['active','completed'])->sum('expected_return'),
            'total_actual'     => Auth::user()->investments()->where('status', 'completed')->sum('actual_return'),
            'active_count'     => Auth::user()->investments()->where('status', 'active')->count(),
            'completed_count'  => Auth::user()->investments()->where('status', 'completed')->count(),
        ];
        return view('client.investments.index', compact('investments', 'summary'));
    }

    public function show(Investment $investment)
    {
        $this->authorize('view', $investment);
        return view('client.investments.show', compact('investment'));
    }
}
