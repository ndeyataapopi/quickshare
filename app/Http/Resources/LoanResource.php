<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status,
            'purpose' => $this->purpose,
            'description' => $this->description,
            'amounts' => [
                'requested' => (float) $this->requested_amount,
                'approved' => (float) $this->approved_amount,
                'funded' => (float) $this->funded_amount,
                'total_repayment' => (float) $this->total_repayment,
            ],
            'terms' => [
                'interest_rate' => (float) $this->interest_rate,
                'platform_fee' => (float) $this->platform_fee,
                'loan_term_days' => $this->loan_term_days,
                'repayment_date' => $this->repayment_date?->toDateString(),
            ],
            'funding_progress' => [
                'funded_amount' => (float) $this->funded_amount,
                'required_amount' => (float) $this->approved_amount,
                'percentage' => $this->approved_amount > 0
                    ? round(($this->funded_amount / $this->approved_amount) * 100, 2)
                    : 0,
                'fully_funded' => (float) $this->funded_amount >= (float) $this->approved_amount,
            ],
            'risk_score' => (float) $this->risk_score,
            'borrower' => new UserResource($this->whenLoaded('borrower')),
            'timestamps' => [
                'submitted_at' => $this->submitted_at?->toIso8601String(),
                'approved_at' => $this->approved_at?->toIso8601String(),
                'disbursed_at' => $this->disbursed_at?->toIso8601String(),
                'completed_at' => $this->completed_at?->toIso8601String(),
                'created_at' => $this->created_at->toIso8601String(),
                'updated_at' => $this->updated_at->toIso8601String(),
            ],
            'rejection_reason' => $this->when(
                ! is_null($this->rejection_reason),
                $this->rejection_reason
            ),
        ];
    }
}
