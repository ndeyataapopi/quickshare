<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $fillable = [
        'user_id',
        'country',
        'city',
        'suburb',
        'street',
        'house_number',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFullAddressAttribute(): string
    {
        return collect([
            $this->house_number,
            $this->street,
            $this->suburb,
            $this->city,
            $this->country,
        ])->filter()->implode(', ');
    }
}
