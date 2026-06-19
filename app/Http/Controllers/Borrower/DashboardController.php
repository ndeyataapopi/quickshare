<?php

namespace App\Http\Controllers\Borrower;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $loans = $user->loans()->latest()->take(5)->get();
        $repayments = $user->repayments()->latest()->take(5)->get();
        $trustScore = $user->trustScore;
        
        return view('borrower.dashboard', compact('user', 'loans', 'repayments', 'trustScore'));
    }
}
