<?php

namespace App\Modules\Users\Listeners;

use App\Models\ActivityLog;
use App\Modules\Users\Events\UserDeactivated;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyUserDeactivation implements ShouldQueue
{
    public function handle(UserDeactivated $event): void
    {
        ActivityLog::create([
            'user_id' => $event->user->id,
            'action' => 'user.deactivated',
            'description' => "User deactivated: {$event->reason}",
            'subject_type' => get_class($event->user),
            'subject_id' => $event->user->id,
            'metadata' => ['reason' => $event->reason],
            'ip_address' => request()->ip(),
        ]);

        // TODO: Send deactivation notification
    }
}
