<?php

namespace App\Http\Controllers\Borrower;

use App\Http\Controllers\Controller;
use App\Modules\Repayments\Models\Repayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RepaymentController extends Controller
{
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
}
