<?php

namespace App\Modules\Auth\Listeners;

use App\Models\ActivityLog;
use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;

class LogPasswordReset
{
    public function handle(PasswordResetEvent $event): void
    {
        ActivityLog::create([
            'user_id' => $event->user->id,
            'actor_id' => $event->user->id,
            'action' => 'user.password_reset',
            'description' => "Password reset for: {$event->user->email}",
            'subject_type' => get_class($event->user),
            'subject_id' => $event->user->id,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
