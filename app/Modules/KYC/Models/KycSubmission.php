<?php

namespace App\Modules\KYC\Models;

use App\Models\User;
use App\Traits\Auditable;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KycSubmission extends Model
{
    use Auditable, HasActivityLog, HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\KycSubmissionFactory::new();
    }

    protected $fillable = [
        'user_id',
        'status',
        'reviewed_by',
        'rejection_reason',
        'admin_notes',
        'metadata',
        'submitted_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(KycDocument::class);
    }

    // ─── Status Helpers ──────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function requiresResubmission(): bool
    {
        return $this->status === 'resubmission_required';
    }

    public function isReviewable(): bool
    {
        return in_array($this->status, ['pending', 'resubmission_required']);
    }

    // ─── Document Checks ─────────────────────────────────────────────

    public function hasAllRequiredDocuments(): bool
    {
        $required = ['national_id', 'payslip', 'bank_statement', 'selfie'];
        $uploaded = $this->documents()->pluck('document_type')->toArray();

        return empty(array_diff($required, $uploaded));
    }

    public function getMissingDocuments(): array
    {
        $required = ['national_id', 'payslip', 'bank_statement', 'selfie'];
        $uploaded = $this->documents()->pluck('document_type')->toArray();

        return array_values(array_diff($required, $uploaded));
    }

    public function allDocumentsScanned(): bool
    {
        return $this->documents()->whereNull('scan_passed')->doesntExist();
    }

    // ─── Scopes ──────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeReviewable($query)
    {
        return $query->whereIn('status', ['pending', 'resubmission_required']);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
