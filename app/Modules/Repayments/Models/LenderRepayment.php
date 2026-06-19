<?php

namespace App\Modules\Repayments\Models;

use App\Models\User;
use App\Modules\Funding\Models\FundingTransaction;
use App\Traits\Auditable;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LenderRepayment extends Model
{
    use Auditable, HasActivityLog;

    protected $fillable = [
        'repayment_id',
        'lender_id',
        'funding_transaction_id',
        'amount',
        'principal_return',
        'interest_earned',
        'penalty_share',
        'funding_percentage',
        'status',
        'processed_at',
        'transaction_reference',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'principal_return' => 'decimal:2',
            'interest_earned' => 'decimal:2',
            'penalty_share' => 'decimal:2',
            'funding_percentage' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────────

    public function repayment(): BelongsTo
    {
        return $this->belongsTo(Repayment::class);
    }

    public function lender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lender_id');
    }

    public function fundingTransaction(): BelongsTo
    {
        return $this->belongsTo(FundingTransaction::class);
    }

    // ─── Status Helpers ──────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessed(): bool
    {
        return $this->status === 'processed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    // ─── Scopes ──────────────────────────────────────────────────────

    public function scopeForRepayment($query, int $repaymentId)
    {
        return $query->where('repayment_id', $repaymentId);
    }

    public function scopeForLender($query, int $lenderId)
    {
        return $query->where('lender_id', $lenderId);
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    // ─── Reference Generator ─────────────────────────────────────────

    public static function generateReference(): string
    {
        do {
            $reference = 'LRPY-' . strtoupper(bin2hex(random_bytes(6)));
        } while (static::where('transaction_reference', $reference)->exists());

        return $reference;
    }
}
