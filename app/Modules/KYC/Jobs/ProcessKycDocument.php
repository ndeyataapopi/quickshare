<?php

namespace App\Modules\KYC\Jobs;

use App\Modules\KYC\Models\KycDocument;
use App\Modules\KYC\Services\FileEncryptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessKycDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public KycDocument $document)
    {
        $this->onQueue('kyc');
    }

    public function handle(FileEncryptionService $encryptionService): void
    {
        Log::info("Processing KYC document: {$this->document->id}");

        // Verify file integrity via hash
        $passed = $this->verifyFileIntegrity($encryptionService);

        // Perform basic content-type validation
        if ($passed) {
            $passed = $this->validateContentType($encryptionService);
        }

        $this->document->update([
            'scan_passed' => $passed,
            'scanned_at' => now(),
        ]);

        Log::info("KYC document {$this->document->id} scan " . ($passed ? 'passed' : 'failed'));
    }

    protected function verifyFileIntegrity(FileEncryptionService $encryptionService): bool
    {
        try {
            // Attempt to decrypt — ensures file isn't corrupted
            $encryptionService->decrypt($this->document->file_path, 'uploads');
            return true;
        } catch (\Throwable $e) {
            Log::warning("KYC document integrity check failed: {$this->document->id}", [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    protected function validateContentType(FileEncryptionService $encryptionService): bool
    {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

        return in_array($this->document->mime_type, $allowedMimes);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("KYC document processing failed: {$this->document->id}", [
            'error' => $exception->getMessage(),
        ]);

        $this->document->update([
            'scan_passed' => false,
            'scanned_at' => now(),
        ]);
    }
}
