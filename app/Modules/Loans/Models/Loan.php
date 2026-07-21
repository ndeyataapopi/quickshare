<?php

namespace App\Modules\Loans\Models;

use App\Models\User;
use App\Modules\Collections\Models\CollectionLog;
use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\Repayments\Models\Repayment;
use App\Traits\Auditable;
use App\Traits\HasActivityLog;
use Database\Factories\LoanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    use Auditable, HasActivityLog, HasFactory;

    protected static function newFactory()
    {
        return LoanFactory::new();
    }

    protected $fillable = [
        'borrower_id',
        'reference',
        'purpose',
        'description',
        'requested_amount',
        'approved_amount',
        'interest_rate',
        'platform_fee',
        'total_repayment',
        'funded_amount',
        'repayment_date',
        'agreement_path',
        'agreement_version',
        'agreement_generated_at',
        'configuration_snapshot',
        'agreement_consent',
        'agreement_ip_address',
        'agreement_user_agent',
        'agreement_consented_at',
        'loan_term_days',
        'status',
        'risk_score',
        'reviewed_by',
        'rejection_reason',
        'admin_notes',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'disbursed_at',
        'external_loan_id',
        'external_provider',
        'sync_status',
        'last_synced_at',
        'external_metadata',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'interest_rate' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'total_repayment' => 'decimal:2',
            'funded_amount' => 'decimal:2',
            'risk_score' => 'decimal:2',
            'repayment_date' => 'date',
            'agreement_generated_at' => 'datetime',
            'configuration_snapshot' => 'array',
            'agreement_consent' => 'array',
            'agreement_consented_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'disbursed_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'external_metadata' => 'array',
            'loan_term_days' => 'integer',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────────

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'borrower_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function fundingTransactions(): HasMany
    {
        return $this->hasMany(FundingTransaction::class);
    }

    public function disbursements(): HasMany
    {
        return $this->hasMany(DisbursementTransaction::class);
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(Repayment::class);
    }

    public function collectionLogs(): HasMany
    {
        return $this->hasMany(CollectionLog::class);
    }

    // ─── Status Helpers ──────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPendingReview(): bool
    {
        return $this->status === 'pending_review';
    }

    public function isOnMarketplace(): bool
    {
        return in_array($this->status, ['marketplace', 'partially_funded']);
    }

    public function isFunded(): bool
    {
        return $this->status === 'funded';
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'disbursed']);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isDefaulted(): bool
    {
        return $this->status === 'defaulted';
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, ['draft', 'pending_review']);
    }

    public function isApprovable(): bool
    {
        return $this->status === 'pending_review';
    }

    public function isDisbursable(): bool
    {
        return $this->status === 'funded';
    }

    // ─── Funding Helpers ─────────────────────────────────────────────

    public function getRemainingFundingAttribute(): float
    {
        $approved = (float) ($this->approved_amount ?? $this->requested_amount);

        return max(0, $approved - (float) $this->funded_amount);
    }

    public function getFundingProgressAttribute(): float
    {
        $approved = (float) ($this->approved_amount ?? $this->requested_amount);
        if ($approved <= 0) {
            return 0;
        }

        return round(((float) $this->funded_amount / $approved) * 100, 2);
    }

    public function getInterestAmountAttribute(): float
    {
        $principal = (float) ($this->approved_amount ?? $this->requested_amount);

        return round((float) $this->total_repayment - $principal - (float) $this->platform_fee, 2);
    }

    public function progress(): float
    {
        $totalRepayment = (float) $this->total_repayment;
        if ($totalRepayment <= 0) {
            return 0;
        }

        $repaidAmount = (float) $this->repayments()->where('status', 'completed')->sum('amount');

        return round(($repaidAmount / $totalRepayment) * 100, 2);
    }

    // ─── Scopes ──────────────────────────────────────────────────────

    public function scopeForBorrower($query, int $borrowerId)
    {
        return $query->where('loans.borrower_id', $borrowerId);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('loans.status', ['active', 'disbursed']);
    }

    public function scopeOnMarketplace($query)
    {
        return $query->whereIn('loans.status', ['marketplace', 'partially_funded']);
    }

    public function scopePendingReview($query)
    {
        return $query->where('loans.status', 'pending_review');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('loans.status', $status);
    }

    // ─── Reference Generator ─────────────────────────────────────────

    public static function generateReference(): string
    {
        do {
            $reference = 'QS-'.strtoupper(bin2hex(random_bytes(6)));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }
}
