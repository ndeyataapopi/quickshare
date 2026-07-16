<?php

namespace App\Modules\KYC\Listeners;

use App\Models\User;
use App\Modules\KYC\Events\KycSubmitted;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyAdminOfKycSubmission implements ShouldQueue
{
    public function __construct(
        protected NotificationService $notificationService,
    ) {
    }

    public function handle(KycSubmitted $event): void
    {
        // Get all admin users
        $admins = User::role('admin')->get();
        
        foreach ($admins as $admin) {
            $this->notificationService->send(
                $admin,
                'kyc_submitted',
                [
                    'user_id' => $event->user->id,
                    'user_name' => $event->user->full_name,
                    'user_email' => $event->user->email,
                    'document_type' => $event->documentType,
                ],
                ['email', 'database']
            );
        }
    }
}
