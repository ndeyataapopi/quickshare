<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FundingTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_reference' => $this->transaction_reference,
            'status' => $this->status,
            'amounts' => [
                'funded' => (float) $this->amount,
                'expected_return' => (float) $this->expected_return,
                'interest_rate' => (float) $this->interest_rate,
            ],
            'loan' => new LoanResource($this->whenLoaded('loan')),
            'lender' => new UserResource($this->whenLoaded('lender')),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
