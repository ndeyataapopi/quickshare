<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RepaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_reference' => $this->transaction_reference,
            'status' => $this->status,
            'amounts' => [
                'total' => (float) $this->amount,
                'principal' => (float) $this->principal,
                'interest' => (float) $this->interest,
                'penalty' => (float) $this->penalty,
                'platform_fee' => (float) $this->platform_fee,
            ],
            'dates' => [
                'due_date' => $this->due_date?->toDateString(),
                'paid_date' => $this->paid_date?->toDateString(),
            ],
            'days_overdue' => $this->days_overdue,
            'payment_method' => $this->payment_method,
            'loan' => new LoanResource($this->whenLoaded('loan')),
            'borrower' => new UserResource($this->whenLoaded('borrower')),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
