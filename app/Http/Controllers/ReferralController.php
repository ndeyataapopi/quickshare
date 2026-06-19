<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ReferralController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $referralCode = $user->referral_code;
        $referrals = $user->referrals()->with('referred')->latest()->get();

        return view('client.referrals', compact('referralCode', 'referrals'));
    }
}
