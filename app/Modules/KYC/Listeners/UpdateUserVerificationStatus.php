<?php

namespace App\Modules\KYC\Listeners;

use App\Modules\KYC\Events\KycApproved;
use App\Modules\KYC\Services\FileEncryptionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UpdateUserVerificationStatus
{
    public function __construct(
        protected FileEncryptionService $encryptionService,
    ) {}

    public function handle(KycApproved $event): void
    {
        $event->user->update(['email_verified_at' => now()]);

        // Promote approved KYC selfie to official profile picture
        $kycSubmission = $event->user->kycSubmission()->with('documents')->first();
        if ($kycSubmission && $kycSubmission->documents) {
            $selfie = $kycSubmission->documents->where('document_type', 'selfie')->first();
            if ($selfie) {
                try {
                    // Decrypt the selfie from the encrypted uploads disk
                    $fileContent = $selfie->is_encrypted
                        ? $this->encryptionService->decrypt($selfie->file_path, 'uploads')
                        : Storage::disk('uploads')->get($selfie->file_path);

                    // Store decrypted image on the public disk
                    $extension = $selfie->mime_type === 'image/png' ? 'png' : 'jpg';
                    $profilePath = "profile-pictures/{$event->user->id}/selfie.{$extension}";
                    Storage::disk('public')->put($profilePath, $fileContent);

                    $event->user->update(['profile_picture' => $profilePath]);
                } catch (\Throwable $e) {
                    Log::error('Failed to set profile picture from KYC selfie', [
                        'user_id' => $event->user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
