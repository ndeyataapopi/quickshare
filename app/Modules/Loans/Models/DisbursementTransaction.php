<?php

namespace App\Modules\Loans\Models;

use App\Traits\Auditable;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisbursementTransaction extends Model
{
    use Auditable, HasActivityLog;

    protected $fillable = [
        'loan_id',
        'direction',
        'gross_amount',
        'platform_fee',
        'net_amount',
        'status',
        'processed_at',
        'transaction_reference',
        'external_reference',
        'payment_method',
        'payment_proof_path',
        'failure_reason',
        'retry_count',
        'next_retry_at',
        'reconciled_at',
        'reconciled_by',
        'reconciliation_data',
        'borrower_confirmed_at',
        'borrower_rejected_at',
        'rejection_reason',
        'ledger_entries',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'processed_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'reconciled_at' => 'datetime',
            'borrower_confirmed_at' => 'datetime',
            'borrower_rejected_at' => 'datetime',
            'reconciliation_data' => 'array',
            'ledger_entries' => 'array',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────────

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    // ─── Status Helpers ──────────────────────────────────────────────

    public function isAwaiting(): bool
    {
        return $this->status === 'awaiting_disbursement';
    }

    public function isAwaitingApproval(): bool
    {
        return $this->status === 'awaiting_approval';
    }

    public function isIncoming(): bool
    {
        return $this->direction === 'incoming';
    }

    public function isOutgoing(): bool
    {
        return $this->direction === 'outgoing';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isDisbursed(): bool
    {
        return $this->status === 'disbursed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isPendingBorrowerConfirmation(): bool
    {
        return $this->status === 'pending_borrower_confirmation';
    }

    public function isRetried(): bool
    {
        return $this->status === 'retried';
    }

    public function isRejectedByBorrower(): bool
    {
        return $this->status === 'rejected_by_borrower';
    }

    public function canRetry(): bool
    {
        return $this->isFailed() && $this->retry_count < 3;
    }

    public function isReconciled(): bool
    {
        return $this->reconciled_at !== null;
    }

    // ─── Scopes ──────────────────────────────────────────────────────

    public function scopeForLoan($query, int $loanId)
    {
        return $query->where('loan_id', $loanId);
    }

    public function scopeAwaiting($query)
    {
        return $query->where('status', 'awaiting_disbursement');
    }

    public function scopeAwaitingApproval($query)
    {
        return $query->where('status', 'awaiting_approval');
    }

    public function scopeIncoming($query)
    {
        return $query->where('direction', 'incoming');
    }

    public function scopeOutgoing($query)
    {
        return $query->where('direction', 'outgoing');
    }

    public function scopePendingProcessing($query)
    {
        return $query->whereIn('status', ['awaiting_disbursement', 'failed'])
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            });
    }

    public function scopeDisbursed($query)
    {
        return $query->where('status', 'disbursed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopePendingBorrowerConfirmation($query)
    {
        return $query->where('status', 'pending_borrower_confirmation');
    }

    public function scopeNeedsRetry($query)
    {
        return $query->where('status', 'failed')
            ->where('retry_count', '<', 3)
            ->where(function ($q) {
                $q->whereNull('next_retry_at')
                    ->orWhere('next_retry_at', '<=', now());
            });
    }

    public function scopeRejectedByBorrower($query)
    {
        return $query->where('status', 'rejected_by_borrower');
    }

    public function scopeReconciliationReady($query)
    {
        return $query->where('status', 'disbursed')
            ->whereNull('reconciled_at');
    }

    // ─── Reference Generator ─────────────────────────────────────────

    public static function generateReference(): string
    {
        do {
            $reference = 'DISB-' . strtoupper(bin2hex(random_bytes(6)));
        } while (static::where('transaction_reference', $reference)->exists());

        return $reference;
    }
}
