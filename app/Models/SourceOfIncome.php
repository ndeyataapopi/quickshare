<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceOfIncome extends Model
{
    protected $fillable = [
        'user_id',
        'profession',
        'company_name',
        'city',
        'country',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isEmployed(): bool
    {
        return in_array($this->profession, ['employed', 'self-employed']);
    }
}
