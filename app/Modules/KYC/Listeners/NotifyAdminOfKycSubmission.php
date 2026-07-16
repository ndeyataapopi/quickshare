<?php

namespace App\Modules\KYC\Listeners;

use App\Models\User;
use App\Modules\KYC\Events\KycSubmitted;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class NotifyAdminOfKycSubmission
{
    public function __construct(
        protected NotificationService $notificationService,
    ) {
    }

    public function handle(KycSubmitted $event): void
    {
        try {
            // Get all admin users
            $admins = User::role('admin')->get();
            
            Log::info('KYC submission notification - Admin users found', [
                'count' => $admins->count(),
                'user_id' => $event->user->id,
                'document_type' => $event->documentType,
            ]);

            if ($admins->count() === 0) {
                Log::warning('No admin users found for KYC notification');
                return;
            }
            
            foreach ($admins as $admin) {
                $result = $this->notificationService->send(
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
                
                Log::info('KYC notification sent to admin', [
                    'admin_id' => $admin->id,
                    'admin_email' => $admin->email,
                    'result' => $result,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send KYC notification to admins', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
