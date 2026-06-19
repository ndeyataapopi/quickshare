<?php

namespace App\Modules\Admin\Models;

use App\Models\User;
use App\Traits\Auditable;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FraudFlag extends Model
{
    use Auditable, HasActivityLog;

    protected $fillable = [
        'subject_type',
        'subject_id',
        'flag_type',
        'severity',
        'status',
        'description',
        'evidence',
        'related_entities',
        'risk_score',
        'detected_by',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'resolution_notes',
        'actions_taken',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'related_entities' => 'array',
            'actions_taken' => 'array',
            'reviewed_at' => 'datetime',
            'risk_score' => 'integer',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────────

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function detector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'detected_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // ─── Status Helpers ────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isUnderReview(): bool
    {
        return $this->status === 'under_review';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isResolved(): bool
    {
        return in_array($this->status, ['confirmed', 'false_positive', 'resolved']);
    }

    // ─── Severity Helpers ────────────────────────────────────────────

    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }

    public function isHighSeverity(): bool
    {
        return $this->severity === 'high' || $this->severity === 'critical';
    }

    // ─── Actions ─────────────────────────────────────────────────────

    public function markUnderReview(int $reviewerId, ?string $notes = null): void
    {
        $this->update([
            'status' => 'under_review',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);
    }

    public function markConfirmed(int $reviewerId, string $resolutionNotes, array $actions = []): void
    {
        $this->update([
            'status' => 'confirmed',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'resolution_notes' => $resolutionNotes,
            'actions_taken' => $actions,
        ]);
    }

    public function markFalsePositive(int $reviewerId, string $resolutionNotes): void
    {
        $this->update([
            'status' => 'false_positive',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'resolution_notes' => $resolutionNotes,
        ]);
    }

    public function markResolved(int $reviewerId, string $resolutionNotes): void
    {
        $this->update([
            'status' => 'resolved',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'resolution_notes' => $resolutionNotes,
        ]);
    }

    // ─── Scopes ──────────────────────────────────────────────────────

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeUnderReview($query)
    {
        return $query->where('status', 'under_review');
    }

    public function scopePendingReview($query)
    {
        return $query->whereIn('status', ['open', 'under_review']);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeHighSeverity($query)
    {
        return $query->whereIn('severity', ['high', 'critical']);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('flag_type', $type);
    }

    public function scopeForSubject($query, string $type, int $id)
    {
        return $query->where('subject_type', $type)->where('subject_id', $id);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->whereDate('created_at', '>=', now()->subDays($days));
    }

    // ─── Risk Score Calculation ──────────────────────────────────────

    public static function calculateRiskScore(string $severity, array $factors = []): int
    {
        $baseScores = [
            'low' => 25,
            'medium' => 50,
            'high' => 75,
            'critical' => 100,
        ];

        $score = $baseScores[$severity] ?? 25;

        // Adjust based on factors
        if (! empty($factors['repeat_offender'])) {
            $score += 20;
        }

        if (! empty($factors['multiple_flags'])) {
            $score += 15;
        }

        if (! empty($factors['financial_impact'])) {
            $score += 10;
        }

        return min(100, $score);
    }
}
