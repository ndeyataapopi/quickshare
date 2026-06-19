<?php

namespace App\Modules\Collections\Models;

use App\Models\User;
use App\Modules\Loans\Models\Loan;
use App\Traits\Auditable;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionCase extends Model
{
    use Auditable, HasActivityLog;

    protected $fillable = [
        'loan_id',
        'borrower_id',
        'assigned_to',
        'status',
        'priority',
        'amount_outstanding',
        'amount_recovered',
        'resolution',
        'resolution_notes',
        'escalation_level',
        'last_contact_date',
        'next_action_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_outstanding' => 'decimal:2',
            'amount_recovered' => 'decimal:2',
            'last_contact_date' => 'datetime',
            'next_action_date' => 'datetime',
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

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    // ─── Status Helpers ──────────────────────────────────────────────

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    // ─── Scopes ──────────────────────────────────────────────────────

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeHighPriority($query)
    {
        return $query->where('priority', 'high');
    }

    public function scopeForLoan($query, int $loanId)
    {
        return $query->where('loan_id', $loanId);
    }

    public function scopeForBorrower($query, int $borrowerId)
    {
        return $query->where('borrower_id', $borrowerId);
    }

    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where('assigned_to', $userId);
    }
}
