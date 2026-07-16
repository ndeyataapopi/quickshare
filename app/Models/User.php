<?php

namespace App\Models;

use App\Modules\TrustScore\Models\TrustScoreHistory;
use App\Modules\TrustScore\Services\TrustScoreService;
use App\Traits\Auditable;
use App\Traits\HasActivityLog;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, Auditable, HasActivityLog, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'national_id',
        'email',
        'phone',
        'date_of_birth',
        'password',
        'referral_code',
        'referred_by',
        'trust_score',
        'status',
        'email_verified_at',
        'phone_verified_at',
        'profile_picture',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'national_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'password' => 'hashed',
            'trust_score' => 'decimal:2',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────────

    public function address(): HasOne
    {
        return $this->hasOne(Address::class);
    }

    public function sourceOfIncome(): HasOne
    {
        return $this->hasOne(SourceOfIncome::class);
    }

    public function referralCode(): HasOne
    {
        return $this->hasOne(ReferralCode::class);
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    public function referredUsers(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    public function currentSession(): HasOne
    {
        return $this->hasOne(UserSession::class)->where('is_current', true);
    }

    public function loginAttempts(): HasMany
    {
        return $this->hasMany(LoginAttempt::class);
    }

    // ─── Accessors ───────────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getReferralCountAttribute(): int
    {
        return $this->referrals()->where('status', 'completed')->count();
    }

    // ─── Status Helpers ──────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isPhoneVerified(): bool
    {
        return $this->phone_verified_at !== null;
    }

    public function isFullyVerified(): bool
    {
        return $this->hasVerifiedEmail() && $this->isPhoneVerified();
    }

    public function activate(): void
    {
        $this->update(['status' => 'active']);
    }

    // ─── Loan Relationships ─────────────────────────────────────────

    public function loans(): HasMany
    {
        return $this->hasMany(\App\Modules\Loans\Models\Loan::class, 'borrower_id');
    }

    public function affordabilityAssessments(): HasMany
    {
        return $this->hasMany(\App\Modules\Loans\Models\AffordabilityAssessment::class);
    }

    // ─── Funding Relationships ───────────────────────────────────────

    public function fundingTransactions(): HasMany
    {
        return $this->hasMany(\App\Modules\Funding\Models\FundingTransaction::class, 'lender_id');
    }

    public function investments(): HasMany
    {
        return $this->hasMany(\App\Modules\Funding\Models\Investment::class, 'lender_id');
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(\App\Modules\Funding\Models\Earning::class, 'lender_id');
    }

    // ─── Repayment Relationships ─────────────────────────────────────

    public function repayments(): HasMany
    {
        return $this->hasMany(\App\Modules\Repayments\Models\Repayment::class, 'borrower_id');
    }

    public function lenderRepayments(): HasMany
    {
        return $this->hasMany(\App\Modules\Repayments\Models\LenderRepayment::class, 'lender_id');
    }

    // ─── Collections Relationships ───────────────────────────────────

    public function collectionLogs(): HasMany
    {
        return $this->hasMany(\App\Modules\Collections\Models\CollectionLog::class, 'borrower_id');
    }

    // ─── KYC Relationships ───────────────────────────────────────────

    public function kycSubmission(): HasOne
    {
        return $this->hasOne(\App\Modules\KYC\Models\KycSubmission::class)->latest();
    }

    // ─── Trust Score Relationships ───────────────────────────────────

    public function trustScoreHistories(): HasMany
    {
        return $this->hasMany(TrustScoreHistory::class);
    }

    // ─── Trust Score Accessors ───────────────────────────────────────

    public function getTrustTierAttribute(): string
    {
        return TrustScoreService::getTier((float) $this->trust_score);
    }

    public function getRiskLevelAttribute(): string
    {
        return TrustScoreService::riskLevel($this);
    }

    public function getMaxLoanAmountAttribute(): float
    {
        return TrustScoreService::maxLoanAmount($this);
    }

    // ─── Trust Score Helpers ─────────────────────────────────────────

    public function canBorrow(): bool
    {
        return TrustScoreService::canBorrow($this);
    }

    public function maxLoanAmount(): float
    {
        return TrustScoreService::maxLoanAmount($this);
    }

    public function riskLevel(): string
    {
        return TrustScoreService::riskLevel($this);
    }
}
