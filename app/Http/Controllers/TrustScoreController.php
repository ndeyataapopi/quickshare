<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Modules\TrustScore\Models\TrustScoreHistory;

class TrustScoreController extends Controller
{
    public function index()
    {
        $trustScore = Auth::user()->trustScore ?? null;
        $score = (float) Auth::user()->trust_score;
        $tier  = \App\Modules\TrustScore\Services\TrustScoreService::getTier($score);
        $maxLoan = \App\Modules\TrustScore\Services\TrustScoreService::maxLoanAmount(Auth::user());
        
        // Get real score history data
        $scoreHistory = TrustScoreHistory::forUser(Auth::id())
            ->orderBy('created_at', 'asc')
            ->take(10)
            ->get();
        
        // Calculate score change in last 30 days
        $thirtyDaysAgo = now()->subDays(30);
        $recentHistory = TrustScoreHistory::forUser(Auth::id())
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->orderBy('created_at', 'asc')
            ->get();
        
        $scoreChange = 0;
        if ($recentHistory->count() >= 2) {
            $scoreChange = $recentHistory->last()->new_score - $recentHistory->first()->new_score;
        }
        
        // Calculate score factors based on user data
        $scoreFactors = $this->calculateScoreFactors(Auth::user());
        
        return view('client.trust-score', compact(
            'trustScore', 
            'score', 
            'tier', 
            'maxLoan', 
            'scoreHistory',
            'scoreChange',
            'scoreFactors'
        ));
    }
    
    private function calculateScoreFactors($user)
    {
        return [
            'repayments' => $this->calculateRepaymentScore($user),
            'kyc_status' => $this->calculateKycScore($user),
            'account_age' => $this->calculateAccountAgeScore($user),
            'referrals' => $this->calculateReferralScore($user),
            'loan_history' => $this->calculateLoanHistoryScore($user),
            'activity' => $this->calculateActivityScore($user),
        ];
    }
    
    private function calculateRepaymentScore($user)
    {
        // Calculate based on on-time repayments
        $repayments = $user->repayments ?? collect();
        if ($repayments->isEmpty()) return 50;
        
        $onTimeCount = $repayments->where('status', 'completed')->count();
        $totalCount = $repayments->count();
        
        return min(100, ($onTimeCount / max(1, $totalCount)) * 100);
    }
    
    private function calculateKycScore($user)
    {
        // Based on KYC status
        $kyc = $user->kycSubmission;
        if (!$kyc) return 0;
        
        return $kyc->status === 'approved' ? 100 : ($kyc->status === 'pending' ? 50 : 0);
    }
    
    private function calculateAccountAgeScore($user)
    {
        // Based on account age
        $daysSinceCreation = $user->created_at->diffInDays(now());
        
        if ($daysSinceCreation >= 365) return 100;
        if ($daysSinceCreation >= 180) return 75;
        if ($daysSinceCreation >= 90) return 50;
        if ($daysSinceCreation >= 30) return 25;
        return 10;
    }
    
    private function calculateReferralScore($user)
    {
        // Based on completed referrals
        $referrals = $user->referrals ?? collect();
        $completedCount = $referrals->where('status', 'completed')->count();
        
        return min(100, $completedCount * 20);
    }
    
    private function calculateLoanHistoryScore($user)
    {
        // Based on loan repayment history
        $loans = $user->loans ?? collect();
        if ($loans->isEmpty()) return 50;
        
        $completedLoans = $loans->where('status', 'completed')->count();
        $defaultedLoans = $loans->where('status', 'defaulted')->count();
        $totalCount = $loans->count();
        
        $baseScore = ($completedLoans / max(1, $totalCount)) * 100;
        $penalty = $defaultedLoans * 20;
        
        return max(0, $baseScore - $penalty);
    }
    
    private function calculateActivityScore($user)
    {
        // Based on recent activity
        $recentActivity = TrustScoreHistory::forUser($user->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        
        return min(100, $recentActivity * 10);
    }
}
