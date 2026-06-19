<?php

namespace App\Modules\TrustScore\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrustScoreHistory extends Model
{
    protected $fillable = [
        'user_id',
        'previous_score',
        'new_score',
        'change',
        'reason',
        'event_type',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'previous_score' => 'decimal:2',
            'new_score' => 'decimal:2',
            'change' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPositive(): bool
    {
        return $this->change > 0;
    }

    public function isNegative(): bool
    {
        return $this->change < 0;
    }

    // ─── Scopes ──────────────────────────────────────────────────────

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePositive($query)
    {
        return $query->where('change', '>', 0);
    }

    public function scopeNegative($query)
    {
        return $query->where('change', '<', 0);
    }

    public function scopeByEvent($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }
}
