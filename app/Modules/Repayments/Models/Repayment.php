<?php

namespace App\Modules\Repayments\Models;

use App\Models\User;
use App\Modules\Loans\Models\Loan;
use App\Traits\Auditable;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Repayment extends Model
{
    use Auditable, HasActivityLog, HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\RepaymentFactory::new();
    }

    protected $fillable = [
        'loan_id',
        'borrower_id',
        'amount',
        'principal',
        'interest',
        'penalty',
        'platform_fee',
        'status',
        'due_date',
        'paid_date',
        'days_overdue',
        'transaction_reference',
        'external_reference',
        'payment_method',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'principal' => 'decimal:2',
            'interest' => 'decimal:2',
            'penalty' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'due_date' => 'date',
            'paid_date' => 'date',
            'metadata' => 'array',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────────

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'borrower_id');
    }

    public function lenderRepayments(): HasMany
    {
        return $this->hasMany(LenderRepayment::class);
    }

    public function collectionLogs(): HasMany
    {
        return $this->hasMany(\App\Modules\Collections\Models\CollectionLog::class);
    }

    // ─── Status Helpers ──────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPartial(): bool
    {
        return $this->status === 'partial';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isOverdue(): bool
    {
        return $this->status === 'overdue';
    }

    public function isDefaulted(): bool
    {
        return $this->status === 'defaulted';
    }

    public function markAsOverdue(): void
    {
        $daysOverdue = now()->diffInDays($this->due_date, false);
        
        $this->update([
            'status' => 'overdue',
            'days_overdue' => max(0, $daysOverdue),
        ]);
    }

    // ─── Scopes ──────────────────────────────────────────────────────

    public function scopeForLoan($query, int $loanId)
    {
        return $query->where('loan_id', $loanId);
    }

    public function scopeForBorrower($query, int $borrowerId)
    {
        return $query->where('borrower_id', $borrowerId);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }

    public function scopeDueToday($query)
    {
        return $query->whereDate('due_date', today());
    }

    public function scopeUpcoming($query, int $days = 7)
    {
        return $query->where('status', 'pending')
            ->whereDate('due_date', '<=', now()->addDays($days))
            ->whereDate('due_date', '>=', today());
    }

    public function scopeShouldBeOverdue($query)
    {
        return $query->whereIn('status', ['pending', 'partial'])
            ->whereDate('due_date', '<', today());
    }

    // ─── Reference Generator ─────────────────────────────────────────

    public static function generateReference(): string
    {
        do {
            $reference = 'REPY-' . strtoupper(bin2hex(random_bytes(6)));
        } while (static::where('transaction_reference', $reference)->exists());

        return $reference;
    }

    // ─── Calculate Penalty ───────────────────────────────────────────

    public function calculatePenalty(): float
    {
        if ($this->days_overdue <= 0) {
            return 0;
        }

        $rate = (float) config('loan.repayment.penalty_rate_weekly', 0.05);
        $maxRatio = (float) config('loan.repayment.max_penalty_ratio', 0.50);

        $weeksOverdue = ceil($this->days_overdue / 7);
        $penalty = $this->amount * ($rate * $weeksOverdue);

        return round(min($penalty, $this->amount * $maxRatio), 2);
    }
}
