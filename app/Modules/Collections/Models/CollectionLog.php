<?php

namespace App\Modules\Collections\Models;

use App\Models\User;
use App\Modules\Loans\Models\Loan;
use App\Modules\Repayments\Models\Repayment;
use App\Traits\Auditable;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionLog extends Model
{
    use Auditable, HasActivityLog;

    protected $fillable = [
        'loan_id',
        'borrower_id',
        'repayment_id',
        'action_type',
        'channel',
        'channel_provider',
        'message',
        'template_used',
        'status',
        'sent_at',
        'delivered_at',
        'opened_at',
        'response_received',
        'response_content',
        'responded_at',
        'external_reference',
        'metadata',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'opened_at' => 'datetime',
            'responded_at' => 'datetime',
            'response_received' => 'boolean',
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

    public function repayment(): BelongsTo
    {
        return $this->belongsTo(Repayment::class);
    }

    // ─── Status Helpers ──────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function markAsSent(?string $externalRef = null): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'external_reference' => $externalRef,
        ]);
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function markAsOpened(): void
    {
        $this->update(['opened_at' => now()]);
    }

    public function markAsFailed(string $reason): void
    {
        $this->update([
            'status' => 'failed',
            'notes' => $reason,
        ]);
    }

    public function recordResponse(string $content): void
    {
        $this->update([
            'response_received' => true,
            'response_content' => $content,
            'responded_at' => now(),
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

    public function scopeByAction($query, string $action)
    {
        return $query->where('action_type', $action);
    }

    public function scopeByChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeReminders($query)
    {
        return $query->where('action_type', 'reminder_sent');
    }

    public function scopeEscalations($query)
    {
        return $query->whereIn('action_type', [
            'escalation_level_1',
            'escalation_level_2',
            'escalation_level_3',
        ]);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->whereDate('created_at', '>=', now()->subDays($days));
    }
}
