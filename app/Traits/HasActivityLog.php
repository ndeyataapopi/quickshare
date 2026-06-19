<?php

namespace App\Traits;

use App\Models\ActivityLog;

trait HasActivityLog
{
    public function activityLogs()
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }

    public function logActivity(string $action, ?string $description = null, array $metadata = []): ActivityLog
    {
        return ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'description' => $description,
            'subject_type' => get_class($this),
            'subject_id' => $this->getKey(),
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public static function logStaticActivity(string $action, ?string $description = null, array $metadata = []): ActivityLog
    {
        return ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'description' => $description,
            'subject_type' => static::class,
            'subject_id' => null,
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
