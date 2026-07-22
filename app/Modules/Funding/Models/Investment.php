<?php

namespace App\Modules\Funding\Models;

use App\Models\User;
use App\Modules\Loans\Models\Loan;
use App\Traits\Auditable;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Investment extends Model
{
    use Auditable, HasActivityLog, HasFactory;

    protected $fillable = [
        'loan_id',
        'lender_id',
        'funding_transaction_id',
        'amount',
        'interest_rate',
        'expected_return',
        'actual_return',
        'status',
        'funded_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'interest_rate' => 'decimal:2',
            'expected_return' => 'decimal:2',
            'actual_return' => 'decimal:2',
            'funded_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────────

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
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

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    // ─── Scopes ──────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeForLender($query, int $lenderId)
    {
        return $query->where('lender_id', $lenderId);
    }

    public function scopeForLoan($query, int $loanId)
    {
        return $query->where('loan_id', $loanId);
    }
}
