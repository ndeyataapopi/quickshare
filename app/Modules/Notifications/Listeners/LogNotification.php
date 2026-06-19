<?php

namespace App\Modules\Notifications\Listeners;

use App\Models\ActivityLog;
use App\Modules\Notifications\Events\NotificationSent;

class LogNotification
{
    public function handle(NotificationSent $event): void
    {
        ActivityLog::create([
            'user_id' => $event->user->id,
            'action' => 'notification.sent',
            'description' => "Notification sent via {$event->channel}: {$event->type}",
            'subject_type' => get_class($event->user),
            'subject_id' => $event->user->id,
            'metadata' => [
                'channel' => $event->channel,
                'type' => $event->type,
            ],
            'ip_address' => request()->ip(),
        ]);
    }
}
