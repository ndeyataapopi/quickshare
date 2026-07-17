<?php

namespace App\Modules\KYC\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\KYC\Events\KycApproved;
use App\Modules\KYC\Events\KycRejected;
use App\Modules\KYC\Events\KycSubmitted;
use App\Modules\KYC\Jobs\ProcessKycDocument;
use App\Modules\KYC\Models\KycDocument;
use App\Modules\KYC\Models\KycSubmission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KycService
{
    protected FileEncryptionService $encryptionService;

    protected array $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
    ];

    protected int $maxFileSizeBytes = 10 * 1024 * 1024; // 10MB

    public function __construct(FileEncryptionService $encryptionService)
    {
        $this->encryptionService = $encryptionService;
    }

    // ─── Submission ──────────────────────────────────────────────────

    public function submitKYC(User $user, array $data): KycSubmission
    {
        $this->ensureCanSubmit($user);

        return DB::transaction(function () use ($user, $data) {
            $submission = KycSubmission::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'submitted_at' => now(),
                'metadata' => [
                    'document_type' => $data['document_type'],
                    'document_number' => $data['document_number'],
                    'issuing_country' => $data['issuing_country'],
                    'expiry_date' => $data['expiry_date'],
                ],
            ]);

            // Store required documents
            $this->storeDocument($submission, $user, 'national_id', $data['national_id']);
            $this->storeDocument($submission, $user, 'selfie', $data['selfie']);
            
            // Store optional documents if provided
            if (isset($data['payslip']) && $data['payslip']) {
                $this->storeDocument($submission, $user, 'payslip', $data['payslip']);
            }
            if (isset($data['bank_statement']) && $data['bank_statement']) {
                $this->storeDocument($submission, $user, 'bank_statement', $data['bank_statement']);
            }

            event(new KycSubmitted($user, 'full_submission'));

            return $submission->load('documents');
        });
    }

    public function submit(User $user, array $documents): KycSubmission
    {
        $this->ensureCanSubmit($user);

        return DB::transaction(function () use ($user, $documents) {
            $submission = KycSubmission::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'submitted_at' => now(),
            ]);

            foreach ($documents as $type => $file) {
                $this->storeDocument($submission, $user, $type, $file);
            }

            $this->validateAllDocumentsPresent($submission);

            event(new KycSubmitted($user, 'full_submission'));

            return $submission->load('documents');
        });
    }

    public function resubmit(User $user, KycSubmission $submission, array $documents): KycSubmission
    {
        if (! $submission->requiresResubmission()) {
            throw new ApiException('This submission does not require resubmission.', 422);
        }

        if ($submission->user_id !== $user->id) {
            throw new ApiException('Unauthorized.', 403);
        }

        return DB::transaction(function () use ($user, $submission, $documents) {
            foreach ($documents as $type => $file) {
                // Delete old document of same type
                $existing = $submission->documents()->where('document_type', $type)->first();
                if ($existing) {
                    $this->deleteStoredFile($existing);
                    $existing->delete();
                }

                $this->storeDocument($submission, $user, $type, $file);
            }

            $submission->update([
                'status' => 'pending',
                'rejection_reason' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'submitted_at' => now(),
            ]);

            event(new KycSubmitted($user, 'resubmission'));

            return $submission->fresh()->load('documents');
        });
    }

    // ─── Admin Review ────────────────────────────────────────────────

    public function approve(KycSubmission $submission, User $reviewer, ?string $notes = null): KycSubmission
    {
        if (! $submission->isReviewable()) {
            throw new ApiException('This submission cannot be reviewed.', 422);
        }

        return DB::transaction(function () use ($submission, $reviewer, $notes) {
            $submission->documents()->update(['status' => 'approved']);

            $submission->update([
                'status' => 'approved',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'admin_notes' => $notes,
                'rejection_reason' => null,
            ]);

            event(new KycApproved($submission->user));

            return $submission->fresh()->load('documents');
        });
    }

    public function reject(
        KycSubmission $submission,
        User $reviewer,
        string $reason,
        array $documentRejections = [],
        bool $allowResubmission = true,
    ): KycSubmission {
        if (! $submission->isReviewable()) {
            throw new ApiException('This submission cannot be reviewed.', 422);
        }

        return DB::transaction(function () use ($submission, $reviewer, $reason, $documentRejections, $allowResubmission) {
            // Reject specific documents if specified
            foreach ($documentRejections as $documentId => $rejectionReason) {
                $submission->documents()
                    ->where('id', $documentId)
                    ->update([
                        'status' => 'rejected',
                        'rejection_reason' => $rejectionReason,
                    ]);
            }

            $status = $allowResubmission ? 'resubmission_required' : 'rejected';

            $submission->update([
                'status' => $status,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            event(new KycRejected($submission->user, $reason));

            return $submission->fresh()->load('documents');
        });
    }

    public function reviewKYC(KycSubmission $submission, string $decision, ?string $notes = null): KycSubmission
    {
        $reviewer = auth()->user();

        if ($decision === 'approve') {
            return $this->approve($submission, $reviewer, $notes);
        }

        return $this->reject($submission, $reviewer, $notes ?? 'KYC rejected by admin');
    }

    // ─── Document Storage ────────────────────────────────────────────

    protected function storeDocument(KycSubmission $submission, User $user, string $type, UploadedFile $file, array $metadata = []): KycDocument
    {
        $this->validateFile($file);

        $hash = hash_file('sha256', $file->getRealPath());
        $storedFilename = Str::uuid() . '.enc';
        $directory = "kyc/{$user->id}/{$submission->id}";
        $filePath = "{$directory}/{$storedFilename}";

        // Store file temporarily then encrypt
        $tempPath = $file->store("kyc/temp", 'uploads');
        $this->encryptionService->encrypt($tempPath, $filePath, 'uploads');
        Storage::disk('uploads')->delete($tempPath);

        $document = KycDocument::create([
            'kyc_submission_id' => $submission->id,
            'user_id' => $user->id,
            'document_type' => $type,
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $storedFilename,
            'file_path' => $filePath,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'file_hash' => $hash,
            'is_encrypted' => true,
        ]);

        // Dispatch async job for scanning
        ProcessKycDocument::dispatch($document);

        return $document;
    }

    protected function validateFile(UploadedFile $file): void
    {
        if (! in_array($file->getMimeType(), $this->allowedMimeTypes)) {
            throw new ApiException(
                "Invalid file type: {$file->getMimeType()}. Allowed: " . implode(', ', $this->allowedMimeTypes),
                422,
            );
        }

        if ($file->getSize() > $this->maxFileSizeBytes) {
            $maxMb = $this->maxFileSizeBytes / 1024 / 1024;
            throw new ApiException("File exceeds maximum size of {$maxMb}MB.", 422);
        }

        // Basic malware-safe extension check
        $dangerousExtensions = ['exe', 'bat', 'cmd', 'sh', 'php', 'js', 'vbs', 'ps1', 'scr'];
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, $dangerousExtensions)) {
            throw new ApiException('File type not allowed for security reasons.', 422);
        }
    }

    // ─── File Retrieval ──────────────────────────────────────────────

    public function getDecryptedDocument(KycDocument $document): string
    {
        if (! $document->is_encrypted) {
            return Storage::disk('uploads')->get($document->file_path);
        }

        return $this->encryptionService->decrypt($document->file_path, 'uploads');
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    protected function ensureCanSubmit(User $user): void
    {
        $activeSubmission = KycSubmission::forUser($user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($activeSubmission?->isApproved()) {
            throw new ApiException('Your KYC has already been approved.', 422);
        }

        if ($activeSubmission?->isPending()) {
            throw new ApiException('You already have a pending KYC submission.', 422);
        }
    }

    protected function validateAllDocumentsPresent(KycSubmission $submission): void
    {
        $missing = $submission->getMissingDocuments();

        if (! empty($missing)) {
            throw new ApiException(
                'Missing required documents: ' . implode(', ', $missing),
                422,
            );
        }
    }

    protected function deleteStoredFile(KycDocument $document): void
    {
        if (Storage::disk('uploads')->exists($document->file_path)) {
            Storage::disk('uploads')->delete($document->file_path);
        }
    }

    public function getUserSubmissions(User $user)
    {
        return KycSubmission::forUser($user->id)
            ->with('documents')
            ->latest()
            ->get();
    }

    public function getPendingSubmissions()
    {
        return KycSubmission::reviewable()
            ->with(['user:id,first_name,last_name,email', 'documents'])
            ->latest('submitted_at')
            ->paginate(20);
    }
}
