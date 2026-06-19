<?php

namespace App\Modules\Auth\Services;

use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\User;
use Illuminate\Support\Str;

class ReferralService
{
    public function getUserReferralCode(User $user): ReferralCode
    {
        return $user->referralCode ?? $this->createReferralCode($user);
    }

    public function createReferralCode(User $user): ReferralCode
    {
        $code = $this->generateUniqueCode();

        return ReferralCode::create([
            'user_id' => $user->id,
            'code' => $code,
            'is_active' => true,
        ]);
    }

    public function validateCode(string $code): ?ReferralCode
    {
        $referralCode = ReferralCode::where('code', $code)
            ->where('is_active', true)
            ->first();

        if (! $referralCode || ! $referralCode->isUsable()) {
            return null;
        }

        return $referralCode;
    }

    public function completeReferral(User $referredUser): void
    {
        $referral = Referral::where('referred_id', $referredUser->id)
            ->where('status', 'pending')
            ->first();

        if ($referral) {
            $referral->complete();
            $this->rewardReferrer($referral->referrer);
        }
    }

    public function getUserReferrals(User $user): array
    {
        $referrals = $user->referrals()
            ->with('referred:id,first_name,last_name,status,created_at')
            ->latest()
            ->get();

        return [
            'referral_code' => $user->referralCode?->code,
            'total_referrals' => $referrals->count(),
            'completed_referrals' => $referrals->where('status', 'completed')->count(),
            'pending_referrals' => $referrals->where('status', 'pending')->count(),
            'referrals' => $referrals,
        ];
    }

    protected function rewardReferrer(User $referrer): void
    {
        // Boost trust score for successful referral
        $newScore = min(100, $referrer->trust_score + 2.00);
        $referrer->update(['trust_score' => $newScore]);
    }

    protected function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (ReferralCode::where('code', $code)->exists());

        return $code;
    }
}
