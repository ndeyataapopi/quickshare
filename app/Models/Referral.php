<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    protected $fillable = [
        'referrer_id',
        'referred_id',
        'referral_code',
        'status',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_id');
    }

    public function complete(): void
    {
        $this->update(['status' => 'completed']);
    }

    public function revoke(): void
    {
        $this->update(['status' => 'revoked']);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
