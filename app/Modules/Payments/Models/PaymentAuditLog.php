<?php

namespace App\Modules\Payments\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAuditLog extends Model
{
    protected $fillable = [
        'operation',
        'payment_method',
        'provider',
        'transaction_type',
        'transaction_id',
        'transaction_reference',
        'provider_reference',
        'event',
        'status',
        'message',
        'expected_amount',
        'reported_amount',
        'metadata',
        'user_id',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'expected_amount' => 'decimal:2',
            'reported_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create an audit entry for a payment operation event.
     */
    public static function log(
        string $operation,
        string $event,
        ?string $status = null,
        ?string $message = null,
        array $context = [],
    ): self {
        $metadata = array_diff_key($context, [
            'operation' => true,
            'payment_method' => true,
            'provider' => true,
            'transaction_type' => true,
            'transaction_id' => true,
            'transaction_reference' => true,
            'provider_reference' => true,
            'expected_amount' => true,
            'reported_amount' => true,
        ]);

        return self::create([
            'operation' => $operation,
            'payment_method' => $context['payment_method'] ?? null,
            'provider' => $context['provider'] ?? null,
            'transaction_type' => $context['transaction_type'] ?? null,
            'transaction_id' => $context['transaction_id'] ?? null,
            'transaction_reference' => $context['transaction_reference'] ?? null,
            'provider_reference' => $context['provider_reference'] ?? null,
            'event' => $event,
            'status' => $status,
            'message' => $message,
            'expected_amount' => $context['expected_amount'] ?? null,
            'reported_amount' => $context['reported_amount'] ?? null,
            'metadata' => $metadata,
            'user_id' => auth()->id(),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
