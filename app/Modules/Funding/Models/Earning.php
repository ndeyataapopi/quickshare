<?php

namespace App\Modules\Funding\Models;

use App\Models\User;
use App\Modules\Loans\Models\Loan;
use App\Traits\Auditable;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Earning extends Model
{
    use Auditable, HasActivityLog;

    protected $fillable = [
        'investment_id',
        'lender_id',
        'loan_id',
        'amount',
        'type',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────────

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }

    public function lender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lender_id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    // ─── Status Helpers ──────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isReceived(): bool
    {
        return $this->status === 'received';
    }

    // ─── Scopes ──────────────────────────────────────────────────────

    public function scopeReceived($query)
    {
        return $query->where('status', 'received');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForLender($query, int $lenderId)
    {
        return $query->where('lender_id', $lenderId);
    }
}
