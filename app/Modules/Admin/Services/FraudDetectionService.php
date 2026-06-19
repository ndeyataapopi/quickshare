<?php

namespace App\Modules\Admin\Services;

use App\Models\User;
use App\Modules\Admin\Events\FraudAlert;
use App\Modules\Admin\Models\FraudFlag;
use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\KYC\Models\KycSubmission;
use App\Modules\Loans\Models\Loan;
use App\Modules\Repayments\Models\Repayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FraudDetectionService
{
    // ─── Fraud Flag Types ────────────────────────────────────────────

    const FLAG_TYPES = [
        'duplicate_identity' => 'Duplicate Identity Document',
        'duplicate_bank_account' => 'Duplicate Bank Account',
        'suspicious_funding_pattern' => 'Suspicious Funding Pattern',
        'rapid_registration' => 'Rapid Registration',
        'fake_referral' => 'Fake Referral',
        'multiple_loans_same_day' => 'Multiple Loans Same Day',
        'rapid_loan_sequence' => 'Rapid Loan Sequence',
        'high_velocity_borrowing' => 'High Velocity Borrowing',
        'referral_abuse' => 'Referral Abuse',
        'location_anomaly' => 'Location Anomaly',
        'device_fingerprint_mismatch' => 'Device Fingerprint Mismatch',
    ];

    // ─── Scan User and Create Fraud Flags ──────────────────────────────

    public function scanUser(User $user, ?int $detectorId = null): array
    {
        $flags = [];
        $detectorId = $detectorId ?? 1; // System user

        // Check duplicate identity
        $duplicateIdentity = $this->checkDuplicateIdentity($user);
        if ($duplicateIdentity) {
            $flags[] = $this->createFraudFlag($user, 'duplicate_identity', 'critical', $duplicateIdentity, $detectorId);
        }

        // Check duplicate bank account
        $duplicateBank = $this->checkDuplicateBankAccount($user);
        if ($duplicateBank) {
            $flags[] = $this->createFraudFlag($user, 'duplicate_bank_account', 'high', $duplicateBank, $detectorId);
        }

        // Check rapid registration
        $rapidReg = $this->checkRapidRegistration($user);
        if ($rapidReg) {
            $flags[] = $this->createFraudFlag($user, 'rapid_registration', 'medium', $rapidReg, $detectorId);
        }

        // Check fake referral
        $fakeReferral = $this->checkFakeReferral($user);
        if ($fakeReferral) {
            $flags[] = $this->createFraudFlag($user, 'fake_referral', 'medium', $fakeReferral, $detectorId);
        }

        // Check for rapid loan sequence
        $rapidLoans = $this->checkRapidLoanSequence($user);
        if ($rapidLoans) {
            $flags[] = $this->createFraudFlag($user, 'rapid_loan_sequence', 'medium', $rapidLoans, $detectorId);
        }

        // Check for multiple loans same day
        $sameDayLoans = $this->checkMultipleLoansSameDay($user);
        if ($sameDayLoans) {
            $flags[] = $this->createFraudFlag($user, 'multiple_loans_same_day', 'high', $sameDayLoans, $detectorId);
        }

        // Check borrowing velocity
        $velocity = $this->checkBorrowingVelocity($user);
        if ($velocity) {
            $flags[] = $this->createFraudFlag($user, 'high_velocity_borrowing', 'medium', $velocity, $detectorId);
        }

        // Check for referral abuse
        $referralAbuse = $this->checkReferralAbuse($user);
        if ($referralAbuse) {
            $flags[] = $this->createFraudFlag($user, 'referral_abuse', 'low', $referralAbuse, $detectorId);
        }

        // Fire alerts for high severity flags
        foreach ($flags as $flag) {
            if ($flag->isHighSeverity()) {
                FraudAlert::dispatch($flag, $user, 'new_flag');
            }
        }

        Log::info('Fraud scan completed', [
            'user_id' => $user->id,
            'flags_found' => count($flags),
        ]);

        return $flags;
    }

    // ─── Create Fraud Flag ─────────────────────────────────────────────

    protected function createFraudFlag(
        User $user,
        string $type,
        string $severity,
        array $evidence,
        int $detectorId,
    ): FraudFlag {
        // Check if flag already exists
        $existing = FraudFlag::forSubject(User::class, $user->id)
            ->byType($type)
            ->pendingReview()
            ->first();

        if ($existing) {
            return $existing;
        }

        $flag = FraudFlag::create([
            'subject_type' => User::class,
            'subject_id' => $user->id,
            'flag_type' => $type,
            'severity' => $severity,
            'status' => 'open',
            'description' => self::FLAG_TYPES[$type] ?? 'Unknown fraud type',
            'evidence' => $evidence,
            'risk_score' => FraudFlag::calculateRiskScore($severity, $evidence),
            'detected_by' => $detectorId,
        ]);

        return $flag;
    }

    // ─── Check Duplicate Identity ──────────────────────────────────────

    public function checkDuplicateIdentity(User $user): ?array
    {
        $kyc = KycSubmission::where('user_id', $user->id)
            ->where('status', 'approved')
            ->first();

        if (! $kyc) {
            return null;
        }

        // Check for duplicate national ID
        $duplicateNationalId = User::where('national_id', $user->national_id)
            ->where('id', '!=', $user->id)
            ->whereNotNull('national_id')
            ->first();

        if ($duplicateNationalId) {
            return [
                'type' => 'duplicate_national_id',
                'national_id' => $user->national_id,
                'duplicate_user_id' => $duplicateNationalId->id,
                'duplicate_user_email' => $duplicateNationalId->email,
            ];
        }

        // Check for duplicate passport
        $passportNumber = $kyc->metadata['passport_number'] ?? null;
        if ($passportNumber) {
            $duplicatePassport = KycSubmission::where('metadata->passport_number', $passportNumber)
                ->where('user_id', '!=', $user->id)
                ->where('status', 'approved')
                ->first();

            if ($duplicatePassport) {
                return [
                    'type' => 'duplicate_passport',
                    'passport_number' => $passportNumber,
                    'duplicate_user_id' => $duplicatePassport->user_id,
                ];
            }
        }

        return null;
    }

    // ─── Check Duplicate Bank Account ──────────────────────────────────

    public function checkDuplicateBankAccount(User $user): ?array
    {
        // Get user's KYC data with bank details
        $kyc = KycSubmission::where('user_id', $user->id)
            ->where('status', 'approved')
            ->first();

        if (! $kyc) {
            return null;
        }

        $bankAccount = $kyc->metadata['bank_account_number'] ?? null;
        $bankCode = $kyc->metadata['bank_code'] ?? null;

        if (! $bankAccount || ! $bankCode) {
            return null;
        }

        // Check for duplicate bank account across users
        // Fetch all approved KYCs and check manually for SQLite compatibility
        $allKycs = KycSubmission::where('status', 'approved')
            ->where('user_id', '!=', $user->id)
            ->get();
            
        $duplicate = null;
        foreach ($allKycs as $kycRecord) {
            $metaBankAccount = $kycRecord->metadata['bank_account_number'] ?? null;
            $metaBankCode = $kycRecord->metadata['bank_code'] ?? null;
            
            if ($metaBankAccount === $bankAccount && $metaBankCode === $bankCode) {
                $duplicate = $kycRecord;
                break;
            }
        }

        if ($duplicate) {
            return [
                'bank_account' => $bankAccount,
                'bank_code' => $bankCode,
                'duplicate_user_id' => $duplicate->user_id,
                'duplicate_user_email' => $duplicate->user->email ?? null,
            ];
        }

        return null;
    }

    // ─── Check Suspicious Funding Pattern ──────────────────────────────

    public function checkSuspiciousFundingPattern(User $user): ?array
    {
        // Check if user is both borrower and lender in suspicious patterns
        $fundingAsLender = FundingTransaction::forLender($user->id)
            ->confirmed()
            ->count();

        $loansAsBorrower = Loan::forBorrower($user->id)
            ->whereIn('status', ['active', 'completed'])
            ->count();

        // Self-funding pattern: User funds loans and also takes loans
        if ($fundingAsLender > 0 && $loansAsBorrower > 0) {
            // Check if they funded their own loans (through same loan)
            $ownLoansFunded = FundingTransaction::forLender($user->id)
                ->whereHas('loan', fn ($q) => $q->where('borrower_id', $user->id))
                ->confirmed()
                ->count();

            if ($ownLoansFunded > 0) {
                return [
                    'pattern' => 'self_funding',
                    'own_loans_funded' => $ownLoansFunded,
                    'total_lender_fundings' => $fundingAsLender,
                    'total_borrower_loans' => $loansAsBorrower,
                ];
            }
        }

        // Check for circular funding patterns
        $recentFundings = FundingTransaction::forLender($user->id)
            ->confirmed()
            ->whereDate('confirmed_at', '>=', now()->subDays(30))
            ->get();

        if ($recentFundings->count() >= 10) {
            $uniqueLoans = $recentFundings->pluck('loan_id')->unique()->count();
            
            // If funding many different loans rapidly, might be money laundering
            if ($uniqueLoans >= 10) {
                return [
                    'pattern' => 'high_velocity_funding',
                    'fundings_last_30_days' => $recentFundings->count(),
                    'unique_loans_funded' => $uniqueLoans,
                    'total_amount' => $recentFundings->sum('amount'),
                ];
            }
        }

        return null;
    }

    // ─── Check Rapid Registration ──────────────────────────────────────

    public function checkRapidRegistration(User $user): ?array
    {
        // Check for multiple registrations from same IP/device
        $registrationsFromSameIp = User::where('id', '!=', $user->id)
            ->whereDate('created_at', '>=', now()->subDays(7))
            ->count();

        // Check if referrer has many rapid signups
        if ($user->referred_by) {
            $referrer = User::find($user->referred_by);
            if ($referrer) {
                $recentReferrals = $referrer->referrals()
                    ->whereDate('created_at', '>=', now()->subDays(7))
                    ->count();

                if ($recentReferrals >= 5) {
                    return [
                        'pattern' => 'referrer_rapid_signups',
                        'referrer_id' => $referrer->id,
                        'recent_referrals' => $recentReferrals,
                        'time_window' => '7_days',
                    ];
                }
            }
        }

        return null;
    }

    // ─── Check Fake Referral ─────────────────────────────────────────

    public function checkFakeReferral(User $user): ?array
    {
        if (! $user->referred_by) {
            return null;
        }

        $referrer = User::find($user->referred_by);

        if (! $referrer) {
            return null;
        }

        // Check for suspicious patterns
        $suspiciousPatterns = [];

        // 1. Same name/email pattern (e.g., john1@, john2@)
        similar_text($user->last_name, $referrer->last_name, $nameSimilarity);
        if ($nameSimilarity > 70) {
            $suspiciousPatterns[] = 'similar_names';
        }

        // 2. Same phone prefix
        $userPhonePrefix = substr($user->phone, 0, 6);
        $referrerPhonePrefix = substr($referrer->phone, 0, 6);
        if ($userPhonePrefix === $referrerPhonePrefix) {
            $suspiciousPatterns[] = 'same_phone_prefix';
        }

        // 3. Registration time proximity
        $hoursApart = $referrer->created_at->diffInHours($user->created_at);
        if ($hoursApart < 24) {
            $suspiciousPatterns[] = 'rapid_referral';
        }

        // 4. Multiple referrals from same user
        $referrerReferralCount = $referrer->referrals()->count();
        if ($referrerReferralCount >= 5) {
            $suspiciousPatterns[] = 'high_referral_volume';
        }

        // If 2 or more suspicious patterns, flag as fake referral
        if (count($suspiciousPatterns) >= 2) {
            return [
                'referrer_id' => $referrer->id,
                'referrer_email' => $referrer->email,
                'patterns_detected' => $suspiciousPatterns,
                'hours_apart' => $hoursApart,
                'name_similarity' => round($nameSimilarity, 2),
                'referrer_total_referrals' => $referrerReferralCount,
            ];
        }

        return null;
    }

    // ─── Check Rapid Loan Sequence ───────────────────────────────────

    protected function checkRapidLoanSequence(User $user): ?array
    {
        // Find loans taken within 30 days of each other
        $loans = Loan::forBorrower($user->id)
            ->whereIn('status', ['active', 'completed'])
            ->whereDate('disbursed_at', '>=', now()->subDays(90))
            ->orderBy('disbursed_at')
            ->get();

        if ($loans->count() < 2) {
            return null;
        }

        $rapidSequences = [];

        for ($i = 0; $i < $loans->count() - 1; $i++) {
            $daysBetween = $loans[$i]->disbursed_at->diffInDays($loans[$i + 1]->disbursed_at);
            
            if ($daysBetween <= 15) { // Less than 15 days between loans
                $rapidSequences[] = [
                    'loan_1' => $loans[$i]->reference,
                    'loan_2' => $loans[$i + 1]->reference,
                    'days_between' => $daysBetween,
                ];
            }
        }

        return count($rapidSequences) > 0 ? $rapidSequences : null;
    }

    // ─── Check Multiple Loans Same Day ─────────────────────────────────

    protected function checkMultipleLoansSameDay(User $user): ?array
    {
        $sameDayLoans = Loan::forBorrower($user->id)
            ->select('created_at', DB::raw('COUNT(*) as count'))
            ->whereDate('created_at', '>=', now()->subDays(30))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->having('count', '>', 1)
            ->get();

        if ($sameDayLoans->isEmpty()) {
            return null;
        }

        return $sameDayLoans->map(fn ($item) => [
            'date' => $item->created_at->toDateString(),
            'loan_count' => $item->count,
        ])->toArray();
    }

    // ─── Check Borrowing Velocity ────────────────────────────────────

    protected function checkBorrowingVelocity(User $user): ?array
    {
        $last30Days = Loan::forBorrower($user->id)
            ->whereDate('disbursed_at', '>=', now()->subDays(30))
            ->count();

        $last90Days = Loan::forBorrower($user->id)
            ->whereDate('disbursed_at', '>=', now()->subDays(90))
            ->count();

        $threshold30 = 2;  // More than 2 loans in 30 days
        $threshold90 = 4; // More than 4 loans in 90 days

        if ($last30Days > $threshold30 || $last90Days > $threshold90) {
            return [
                'loans_last_30_days' => $last30Days,
                'loans_last_90_days' => $last90Days,
                'threshold_30' => $threshold30,
                'threshold_90' => $threshold90,
            ];
        }

        return null;
    }

    // ─── Check Referral Abuse ──────────────────────────────────────────

    protected function checkReferralAbuse(User $user): ?array
    {
        // Check if user has suspicious referral patterns
        // e.g., referred many users who all defaulted

        if (! $user->referrals()->exists()) {
            return null;
        }

        $referrals = $user->referrals()->with('referredUser.loans')->get();
        
        $defaultedCount = 0;
        $activeCount = 0;
        $totalReferrals = $referrals->count();

        foreach ($referrals as $referral) {
            $referredUser = $referral->referredUser;
            
            if ($referredUser && $referredUser->loans()->where('status', 'defaulted')->exists()) {
                $defaultedCount++;
            } elseif ($referredUser && $referredUser->loans()->where('status', 'active')->exists()) {
                $activeCount++;
            }
        }

        $defaultRate = $totalReferrals > 0 ? ($defaultedCount / $totalReferrals) * 100 : 0;

        // Flag if more than 50% of referrals defaulted
        if ($defaultRate > 50 && $totalReferrals >= 3) {
            return [
                'total_referrals' => $totalReferrals,
                'defaulted_referrals' => $defaultedCount,
                'active_referrals' => $activeCount,
                'default_rate' => round($defaultRate, 2),
            ];
        }

        return null;
    }

    // ─── Get Platform Fraud Summary ──────────────────────────────────

    public function getFraudSummary(): array
    {
        return [
            'users_with_flags' => $this->getUsersWithFlagsCount(),
            'high_risk_users' => $this->getHighRiskUsersCount(),
            'medium_risk_users' => $this->getMediumRiskUsersCount(),
            'recent_flags' => $this->getRecentFlags(),
            'flags_by_type' => $this->getFlagsByType(),
        ];
    }

    protected function getUsersWithFlagsCount(): int
    {
        return FraudFlag::pendingReview()->distinct('subject_id')->count('subject_id');
    }

    protected function getHighRiskUsersCount(): int
    {
        return FraudFlag::highSeverity()->pendingReview()->distinct('subject_id')->count('subject_id');
    }

    protected function getMediumRiskUsersCount(): int
    {
        return FraudFlag::bySeverity('medium')->pendingReview()->distinct('subject_id')->count('subject_id');
    }

    protected function getRecentFlags(): array
    {
        return FraudFlag::recent(7)
            ->with('subject:id,first_name,last_name,email')
            ->latest()
            ->limit(10)
            ->get()
            ->toArray();
    }

    protected function getFlagsByType(): array
    {
        return FraudFlag::pendingReview()
            ->selectRaw('flag_type, COUNT(*) as count')
            ->groupBy('flag_type')
            ->pluck('count', 'flag_type')
            ->toArray();
    }

    // ─── Scan All Users (Background Job) ─────────────────────────────

    public function scanAllUsers(?int $detectorId = null): array
    {
        $stats = [
            'scanned' => 0,
            'flags_found' => 0,
            'high_risk' => 0,
            'critical' => 0,
        ];

        User::where('status', 'active')
            ->chunk(100, function ($users) use (&$stats, $detectorId) {
                foreach ($users as $user) {
                    $flags = $this->scanUser($user, $detectorId);
                    
                    $stats['scanned']++;
                    $stats['flags_found'] += count($flags);
                    
                    $severityCounts = collect($flags)->countBy('severity');
                    
                    if ($severityCounts->get('high', 0) > 0 || $severityCounts->get('critical', 0) > 0) {
                        $stats['high_risk']++;
                    }
                    
                    if ($severityCounts->get('critical', 0) > 0) {
                        $stats['critical']++;
                    }
                }
            });

        return $stats;
    }

    // ─── Admin Review Queue ─────────────────────────────────────────────

    public function getReviewQueue(array $filters = []): array
    {
        $query = FraudFlag::pendingReview()
            ->with(['subject', 'detector'])
            ->orderByDesc('risk_score');

        if (! empty($filters['severity'])) {
            $query->bySeverity($filters['severity']);
        }

        if (! empty($filters['flag_type'])) {
            $query->byType($filters['flag_type']);
        }

        $flags = $query->paginate($filters['per_page'] ?? 20);

        return [
            'flags' => $flags->items(),
            'meta' => [
                'current_page' => $flags->currentPage(),
                'last_page' => $flags->lastPage(),
                'per_page' => $flags->perPage(),
                'total' => $flags->total(),
            ],
        ];
    }

    public function getFlagDetails(int $flagId): ?FraudFlag
    {
        return FraudFlag::with(['subject', 'detector', 'reviewer'])->find($flagId);
    }

    // ─── Resolve Fraud Alert ─────────────────────────────────────────────

    public function resolveAlert(FraudFlag $alert, array $data): void
    {
        $alert->update([
            'status' => 'resolved',
            'resolution' => $data['decision'],
            'resolution_notes' => $data['notes'] ?? null,
            'action_taken' => $data['action'] ?? null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        // Apply actions based on decision
        if ($data['action'] === 'suspend_account' && $alert->subject) {
            $alert->subject->update(['status' => 'suspended']);
        }

        if ($data['action'] === 'freeze_loans' && $alert->subject) {
            $alert->subject->loans()->where('status', 'active')->update(['status' => 'frozen']);
        }
    }
}
