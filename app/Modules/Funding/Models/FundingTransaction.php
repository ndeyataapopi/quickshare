<?php

namespace App\Modules\Funding\Models;

use App\Models\User;
use App\Modules\Loans\Models\Loan;
use App\Traits\Auditable;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FundingTransaction extends Model
{
    use Auditable, HasActivityLog, HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\FundingTransactionFactory::new();
    }

    protected $fillable = [
        'loan_id',
        'lender_id',
        'amount',
        'interest_rate',
        'expected_return',
        'status',
        'confirmed_at',
        'transaction_reference',
        'payment_method',
        'payment_method_detail',
        'payment_reference',
        'payment_proof_path',
        'payment_date',
        'admin_verified_at',
        'admin_verified_by',
        'admin_notes',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'interest_rate' => 'decimal:2',
            'expected_return' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'payment_date' => 'datetime',
            'admin_verified_at' => 'datetime',
            'metadata' => 'array',
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

    public function investment(): HasOne
    {
        return $this->hasOne(Investment::class);
    }

    // ─── Status Helpers ──────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    // ─── Scopes ──────────────────────────────────────────────────────

    public function scopeForLoan($query, int $loanId)
    {
        return $query->where('loan_id', $loanId);
    }

    public function scopeForLender($query, int $lenderId)
    {
        return $query->where('lender_id', $lenderId);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'confirmed']);
    }

    // ─── Reference Generator ─────────────────────────────────────────

    public static function generateReference(): string
    {
        do {
            $reference = 'FUND-' . strtoupper(bin2hex(random_bytes(6)));
        } while (static::where('transaction_reference', $reference)->exists());

        return $reference;
    }
}
