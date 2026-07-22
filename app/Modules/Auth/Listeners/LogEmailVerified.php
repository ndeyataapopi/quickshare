<?php

namespace App\Modules\Auth\Listeners;

use App\Models\ActivityLog;
use Illuminate\Auth\Events\Verified;

class LogEmailVerified
{
    public function handle(Verified $event): void
    {
        ActivityLog::create([
            'user_id' => $event->user->id,
            'actor_id' => $event->user->id,
            'action' => 'user.email_verified',
            'description' => "Email verified: {$event->user->email}",
            'subject_type' => get_class($event->user),
            'subject_id' => $event->user->id,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
