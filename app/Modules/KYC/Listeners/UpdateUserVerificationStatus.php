<?php

namespace App\Modules\KYC\Listeners;

use App\Modules\KYC\Events\KycApproved;
use Illuminate\Support\Facades\Storage;

class UpdateUserVerificationStatus
{
    public function handle(KycApproved $event): void
    {
        $event->user->update(['email_verified_at' => now()]);

        // Save selfie as profile picture
        $kycSubmission = $event->user->kycSubmission;
        if ($kycSubmission && $kycSubmission->documents) {
            $selfie = $kycSubmission->documents->where('document_type', 'selfie')->first();
            if ($selfie) {
                // Get the decrypted selfie file
                $fileContent = Storage::disk('uploads')->get($selfie->file_path);
                
                // Store as profile picture
                $profilePath = "profile-pictures/{$event->user->id}/selfie.jpg";
                Storage::disk('public')->put($profilePath, $fileContent);
                
                // Update user's profile picture
                $event->user->update(['profile_picture' => $profilePath]);
            }
        }
    }
}
