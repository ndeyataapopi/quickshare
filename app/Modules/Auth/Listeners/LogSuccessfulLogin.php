<?php

namespace App\Modules\Auth\Listeners;

use App\Models\ActivityLog;
use App\Modules\Auth\Events\UserLoggedIn;

class LogSuccessfulLogin
{
    public function handle(UserLoggedIn $event): void
    {
        ActivityLog::create([
            'user_id' => $event->user->id,
            'action' => 'user.logged_in',
            'description' => "User logged in: {$event->user->email}",
            'subject_type' => get_class($event->user),
            'subject_id' => $event->user->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
