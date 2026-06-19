<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        // Get user's loans (as borrower)
        $loans = $user->loans()->latest()->take(5)->get();
        
        // Get user's investments (as lender)
        $investments = $user->investments()->whereHas('loan', fn($q) => $q->where('status', 'active'))->latest()->take(5)->get();
        
        // Get user's repayments
        $repayments = $user->repayments()->latest()->take(5)->get();
        
        $earnings = $user->investments()->latest()->take(5)->get();

        // Get trust score
        $trustScore = $user->trustScore;
        
        return view('client.dashboard', compact('user', 'loans', 'investments', 'repayments', 'earnings', 'trustScore'));
    }

    // //lender
    // public function index()
    // {
    //     // $user = Auth::user();
    //     $investments = $user->investments()->whereHas('loan', fn($q) => $q->where('status', 'active'))->latest()->take(5)->get();
    //     $earnings = $user->investments()->latest()->take(5)->get();
        
    //     return view('lender.dashboard', compact('user', 'investments', 'earnings'));
    // }

    // //borrower
    // public function index()
    // {
    //     // $user = Auth::user();
    //     $loans = $user->loans()->latest()->take(5)->get();
    //     $repayments = $user->repayments()->latest()->take(5)->get();
    //     $trustScore = $user->trustScore;
        
    //     return view('borrower.dashboard', compact('user', 'loans', 'repayments', 'trustScore'));
    // }
}
