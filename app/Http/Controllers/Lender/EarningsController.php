<?php

namespace App\Http\Controllers\Lender;

use App\Http\Controllers\Controller;
use App\Modules\Funding\Models\Investment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EarningsController extends Controller
{
    public function index()
    {
        $earnings      = Auth::user()->investments()->with('loan')->where('status', 'completed')->latest('completed_at')->paginate(20);
        $totalEarnings = Auth::user()->investments()->where('status', 'completed')->sum('actual_return');
        $totalInvested = Auth::user()->investments()->whereIn('status', ['active', 'completed'])->sum('amount');
        $activeCount   = Auth::user()->investments()->where('status', 'active')->count();
        return view('client.earnings.index', compact('earnings', 'totalEarnings', 'totalInvested', 'activeCount'));
    }
}
