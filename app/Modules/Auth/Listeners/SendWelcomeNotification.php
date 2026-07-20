<?php

namespace App\Modules\Auth\Listeners;

use App\Models\ActivityLog;
use App\Modules\Auth\Events\UserRegistered;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendWelcomeNotification implements ShouldQueue
{
    public function __construct(
        protected NotificationService $notificationService,
    ) {
    }

    public function handle(UserRegistered $event): void
    {
        ActivityLog::create([
            'user_id' => $event->user->id,
            'action' => 'user.registered',
            'description' => "New user registered: {$event->user->email}",
            'subject_type' => get_class($event->user),
            'subject_id' => $event->user->id,
            'ip_address' => request()->ip(),
        ]);

        // Send welcome notification
        $this->notificationService->send(
            $event->user,
            'welcome',
            [],
            ['email', 'database']
        );
    }
}
