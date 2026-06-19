<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TrustScoreController extends Controller
{
    public function index()
    {
        $trustScore = Auth::user()->trustScore ?? null;
        $score = (float) Auth::user()->trust_score;
        $tier  = \App\Modules\TrustScore\Services\TrustScoreService::getTier($score);
        $maxLoan = \App\Modules\TrustScore\Services\TrustScoreService::maxLoanAmount(Auth::user());
        return view('client.trust-score', compact('trustScore', 'score', 'tier', 'maxLoan'));
    }
}
