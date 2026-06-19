<?php

namespace App\Modules\TrustScore\Services;

use App\Models\User;
use App\Modules\TrustScore\Events\TrustScoreCalculated;
use App\Modules\TrustScore\Models\TrustScoreHistory;
use Illuminate\Support\Facades\DB;

class TrustScoreService
{
    // ─── Score Adjustment Weights ────────────────────────────────────

    const WEIGHT_REPAYMENT_ON_TIME = +3.00;
    const WEIGHT_REPAYMENT_LATE = -5.00;
    const WEIGHT_REPAYMENT_DEFAULT = -15.00;
    const WEIGHT_LOAN_FULLY_REPAID = +5.00;
    const WEIGHT_KYC_APPROVED = +10.00;
    const WEIGHT_REFERRAL_COMPLETED = +2.00;
    const WEIGHT_REFERRAL_DEFAULTED = -3.00;

    const MIN_SCORE = 0.00;
    const MAX_SCORE = 100.00;
    const DEFAULT_SCORE = 50.00;

    // ─── Tier Thresholds ─────────────────────────────────────────────

    const TIER_PLATINUM_MIN = 85.00;
    const TIER_GOLD_MIN = 70.00;
    const TIER_SILVER_MIN = 50.00;
    // Below SILVER_MIN = bronze

    // ─── Loan Limits Per Tier (fallback — driven by config/loans.php tier_limits) ─

    const LOAN_LIMIT_BRONZE   = 500.00;
    const LOAN_LIMIT_SILVER   = 1000.00;
    const LOAN_LIMIT_GOLD     = 1500.00;
    const LOAN_LIMIT_PLATINUM = 1500.00;

    const MIN_BORROW_SCORE = 30.00;

    // ─── Core Adjustment ─────────────────────────────────────────────

    public function adjustScore(
        User $user,
        float $change,
        string $reason,
        string $eventType,
        array $metadata = [],
    ): User {
        return DB::transaction(function () use ($user, $change, $reason, $eventType, $metadata) {
            $previousScore = (float) $user->trust_score;
            $newScore = $this->clamp($previousScore + $change);

            $user->update(['trust_score' => $newScore]);

            TrustScoreHistory::create([
                'user_id' => $user->id,
                'previous_score' => $previousScore,
                'new_score' => $newScore,
                'change' => $change,
                'reason' => $reason,
                'event_type' => $eventType,
                'metadata' => $metadata,
            ]);

            event(new TrustScoreCalculated($user->fresh(), $previousScore, $newScore));

            return $user->fresh();
        });
    }

    // ─── Event-Driven Adjustments ────────────────────────────────────

    public function onRepaymentMade(User $borrower, float $amount, int $loanId): User
    {
        return $this->adjustScore(
            $borrower,
            self::WEIGHT_REPAYMENT_ON_TIME,
            'On-time repayment received.',
            'repayment_on_time',
            ['loan_id' => $loanId, 'amount' => $amount],
        );
    }

    public function onRepaymentOverdue(User $borrower, int $daysOverdue, int $loanId): User
    {
        // Scale penalty by how many days overdue (capped at default weight)
        $penalty = max(
            self::WEIGHT_REPAYMENT_DEFAULT,
            self::WEIGHT_REPAYMENT_LATE - ($daysOverdue * 0.5),
        );

        return $this->adjustScore(
            $borrower,
            $penalty,
            "Repayment overdue by {$daysOverdue} days.",
            'repayment_overdue',
            ['loan_id' => $loanId, 'days_overdue' => $daysOverdue],
        );
    }

    public function onLoanDefault(User $borrower, int $loanId): User
    {
        return $this->adjustScore(
            $borrower,
            self::WEIGHT_REPAYMENT_DEFAULT,
            'Loan defaulted.',
            'loan_default',
            ['loan_id' => $loanId],
        );
    }

    public function onLoanFullyRepaid(User $borrower, int $loanId): User
    {
        return $this->adjustScore(
            $borrower,
            self::WEIGHT_LOAN_FULLY_REPAID,
            'Loan fully repaid.',
            'loan_fully_repaid',
            ['loan_id' => $loanId],
        );
    }

    public function onKycApproved(User $user): User
    {
        return $this->adjustScore(
            $user,
            self::WEIGHT_KYC_APPROVED,
            'KYC verification approved.',
            'kyc_approved',
        );
    }

    public function onReferralCompleted(User $referrer, int $referredUserId): User
    {
        return $this->adjustScore(
            $referrer,
            self::WEIGHT_REFERRAL_COMPLETED,
            'Referred user completed verification.',
            'referral_completed',
            ['referred_user_id' => $referredUserId],
        );
    }

    public function onReferralDefaulted(User $referrer, int $referredUserId): User
    {
        return $this->adjustScore(
            $referrer,
            self::WEIGHT_REFERRAL_DEFAULTED,
            'Referred user defaulted on a loan.',
            'referral_defaulted',
            ['referred_user_id' => $referredUserId],
        );
    }

    // ─── Trust Tier Helpers ──────────────────────────────────────────

    public static function getTier(float $score): string
    {
        $platinumMin = (float) config('loans.trust_score.tier_platinum_min', self::TIER_PLATINUM_MIN);
        $goldMin     = (float) config('loans.trust_score.tier_gold_min',     self::TIER_GOLD_MIN);
        $silverMin   = (float) config('loans.trust_score.tier_silver_min',   self::TIER_SILVER_MIN);

        return match (true) {
            $score >= $platinumMin => 'platinum',
            $score >= $goldMin    => 'gold',
            $score >= $silverMin  => 'silver',
            default               => 'bronze',
        };
    }

    public static function canBorrow(User $user): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        $minScore = (float) config('loans.trust_score.min_borrow_score', self::MIN_BORROW_SCORE);
        return (float) $user->trust_score >= $minScore;
    }

    public static function maxLoanAmount(User $user): float
    {
        if (! self::canBorrow($user)) {
            return 0.00;
        }

        $tier   = self::getTier((float) $user->trust_score);
        $limits = config('loans.tier_limits', [
            'bronze'   => self::LOAN_LIMIT_BRONZE,
            'silver'   => self::LOAN_LIMIT_SILVER,
            'gold'     => self::LOAN_LIMIT_GOLD,
            'platinum' => self::LOAN_LIMIT_PLATINUM,
        ]);

        return (float) ($limits[$tier] ?? self::LOAN_LIMIT_BRONZE);
    }

    public static function riskLevel(User $user): string
    {
        $score = (float) $user->trust_score;

        return match (true) {
            $score >= 80 => 'low',
            $score >= 60 => 'moderate',
            $score >= 40 => 'elevated',
            $score >= 20 => 'high',
            default => 'critical',
        };
    }

    // ─── History & Stats ─────────────────────────────────────────────

    public function getHistory(User $user, int $limit = 20)
    {
        return TrustScoreHistory::forUser($user->id)
            ->latest()
            ->take($limit)
            ->get();
    }

    public function getScoreSummary(User $user): array
    {
        $score = (float) $user->trust_score;

        return [
            'current_score' => $score,
            'tier' => self::getTier($score),
            'risk_level' => self::riskLevel($user),
            'can_borrow' => self::canBorrow($user),
            'max_loan_amount' => self::maxLoanAmount($user),
            'total_positive_events' => TrustScoreHistory::forUser($user->id)->positive()->count(),
            'total_negative_events' => TrustScoreHistory::forUser($user->id)->negative()->count(),
        ];
    }

    // ─── Internal ────────────────────────────────────────────────────

    protected function clamp(float $score): float
    {
        return max(self::MIN_SCORE, min(self::MAX_SCORE, round($score, 2)));
    }
}
