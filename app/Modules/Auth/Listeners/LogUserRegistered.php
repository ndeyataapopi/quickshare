<?php

namespace App\Modules\Auth\Listeners;

use App\Models\ActivityLog;
use App\Modules\Auth\Events\UserRegistered;

class LogUserRegistered
{
    public function handle(UserRegistered $event): void
    {
        ActivityLog::create([
            'user_id' => $event->user->id,
            'actor_id' => $event->user->id,
            'action' => 'user.registered',
            'description' => "New user registered: {$event->user->email}",
            'subject_type' => get_class($event->user),
            'subject_id' => $event->user->id,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
