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
    ) {
    }

    public function handle(KycApproved $event): void
    {
        $event->user->update(['email_verified_at' => now()]);

        // Save selfie as profile picture
        $kycSubmission = $event->user->kycSubmission;
        if ($kycSubmission && $kycSubmission->documents) {
            $selfie = $kycSubmission->documents->where('document_type', 'selfie')->first();
            if ($selfie) {
                try {
                    // Get the encrypted selfie file
                    $encryptedPath = $selfie->file_path;
                    
                    // Decrypt the file to a temporary location
                    $tempPath = Storage::disk('uploads')->path($encryptedPath);
                    $decryptedContent = $this->encryptionService->decrypt($tempPath);
                    
                    // Store as profile picture
                    $profilePath = "profile-pictures/{$event->user->id}/selfie.jpg";
                    Storage::disk('public')->put($profilePath, $decryptedContent);
                    
                    // Update user's profile picture
                    $event->user->update(['profile_picture' => $profilePath]);
                    
                    Log::info('Profile picture saved from KYC selfie', [
                        'user_id' => $event->user->id,
                        'profile_path' => $profilePath,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('Failed to save profile picture from KYC selfie', [
                        'user_id' => $event->user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
