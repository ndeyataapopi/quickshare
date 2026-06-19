<?php

namespace App\Traits;

use App\Models\AuditLog;

trait Auditable
{
    protected array $sensitiveFields = [
        'password', 'token', 'secret', 'api_key', 'access_token',
        'refresh_token', 'ssn', 'social_security_number', 'bank_account',
        'credit_card', 'cvv', 'pin', 'otp', 'verification_code',
        'external_metadata', 'encrypted_*', 'national_id',
    ];

    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            $model->logAudit('created', [], $model->getAttributes());
        });

        static::updated(function ($model) {
            $original = $model->getOriginal();
            $changes = $model->getChanges();

            $before = array_intersect_key($original, $changes);
            unset($changes['updated_at']);
            unset($before['updated_at']);

            if (! empty($changes)) {
                $model->logAudit('updated', $before, $changes);
            }
        });

        static::deleted(function ($model) {
            $model->logAudit('deleted', $model->getOriginal(), []);
        });
    }

    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    protected function logAudit(string $event, array $oldValues, array $newValues): void
    {
        AuditLog::create([
            'auditable_type' => get_class($this),
            'auditable_id' => $this->getKey(),
            'event' => $event,
            'old_values' => $this->maskSensitiveData($oldValues),
            'new_values' => $this->maskSensitiveData($newValues),
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    protected function maskSensitiveData(array $data): array
    {
        $masked = [];

        foreach ($data as $key => $value) {
            if ($this->isSensitiveField($key)) {
                $masked[$key] = $this->maskValue($value);
            } elseif (is_array($value)) {
                $masked[$key] = $this->maskSensitiveData($value);
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }

    protected function isSensitiveField(string $key): bool
    {
        foreach ($this->sensitiveFields as $pattern) {
            if (str_ends_with($pattern, '*')) {
                $prefix = str_replace('*', '', $pattern);
                if (str_starts_with($key, $prefix)) {
                    return true;
                }
            } elseif ($key === $pattern) {
                return true;
            }
        }

        return false;
    }

    protected function maskValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $stringValue = is_string($value) ? $value : json_encode($value);
        $length = strlen($stringValue);

        if ($length <= 4) {
            return '****';
        }

        return substr($stringValue, 0, 2) . str_repeat('*', $length - 4) . substr($stringValue, -2);
    }
}
