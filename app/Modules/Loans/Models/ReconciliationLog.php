<?php

namespace App\Modules\Loans\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationLog extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\ReconciliationLogFactory::new();
    }

    protected $fillable = [
        'loan_id',
        'external_loan_id',
        'provider',
        'operation',
        'direction',
        'status',
        'request_payload',
        'response_payload',
        'error_message',
        'http_status',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'http_status' => 'integer',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────────

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    // ─── Status Helpers ──────────────────────────────────────────────

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    // ─── Scopes ────────────────────────────────────────────────────────

    public function scopeForLoan($query, int $loanId)
    {
        return $query->where('loan_id', $loanId);
    }

    public function scopeForProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeByOperation($query, string $operation)
    {
        return $query->where('operation', $operation);
    }
}
