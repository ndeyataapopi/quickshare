<?php

namespace App\Modules\KYC\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycDocument extends Model
{
    protected $fillable = [
        'kyc_submission_id',
        'user_id',
        'document_type',
        'original_filename',
        'stored_filename',
        'file_path',
        'mime_type',
        'file_size',
        'file_hash',
        'is_encrypted',
        'status',
        'rejection_reason',
        'scan_passed',
        'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
            'scan_passed' => 'boolean',
            'scanned_at' => 'datetime',
            'file_size' => 'integer',
        ];
    }

    // ─── Relationships ───────────────────────────────────────────────

    public function submission(): BelongsTo
    {
        return $this->belongsTo(KycSubmission::class, 'kyc_submission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isScanSafe(): bool
    {
        return $this->scan_passed === true;
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        return round($bytes / 1024, 2) . ' KB';
    }

    public function getDocumentLabel(): string
    {
        return match ($this->document_type) {
            'national_id' => 'National ID/Passport',
            'payslip' => 'Payslip',
            'bank_statement' => '3-Month Bank Statement',
            'selfie' => 'Selfie with Document',
            default => $this->document_type,
        };
    }
}
