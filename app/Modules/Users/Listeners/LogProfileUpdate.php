<?php

namespace App\Modules\Users\Listeners;

use App\Models\ActivityLog;
use App\Modules\Users\Events\UserProfileUpdated;

class LogProfileUpdate
{
    public function handle(UserProfileUpdated $event): void
    {
        ActivityLog::create([
            'user_id' => $event->user->id,
            'action' => 'user.profile_updated',
            'description' => 'User updated their profile',
            'subject_type' => get_class($event->user),
            'subject_id' => $event->user->id,
            'metadata' => ['changed_fields' => $event->changedFields],
            'ip_address' => request()->ip(),
        ]);
    }
}
