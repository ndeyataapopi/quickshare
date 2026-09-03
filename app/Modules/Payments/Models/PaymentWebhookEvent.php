<?php

namespace App\Modules\Payments\Models;

use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\Loans\Models\DisbursementTransaction;
use App\Modules\Repayments\Models\LenderRepayment;
use App\Modules\Repayments\Models\Repayment;
use App\Traits\Auditable;
use App\Traits\HasActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;

class PaymentWebhookEvent extends Model
{
    use Auditable, HasActivityLog;

    public const STATUS_RECEIVED = 'received';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_DUPLICATE = 'duplicate';
    public const STATUS_INVALID = 'invalid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_IGNORED = 'ignored';

    protected $fillable = [
        'provider',
        'provider_event_id',
        'provider_reference',
        'event_type',
        'payload',
        'signature',
        'ip_address',
        'status',
        'transaction_type',
        'transaction_id',
        'processed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function transaction(): ?Relation
    {
        return match ($this->transaction_type) {
            'funding_transaction' => $this->belongsTo(FundingTransaction::class, 'transaction_id'),
            'disbursement_transaction' => $this->belongsTo(DisbursementTransaction::class, 'transaction_id'),
            'repayment' => $this->belongsTo(Repayment::class, 'transaction_id'),
            'lender_repayment' => $this->belongsTo(LenderRepayment::class, 'transaction_id'),
            default => null,
        };
    }
}
