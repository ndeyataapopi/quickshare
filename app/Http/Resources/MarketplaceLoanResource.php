<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketplaceLoanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status,
            'amounts' => [
                'requested' => (float) $this->requested_amount,
                'approved' => (float) $this->approved_amount,
                'funded' => (float) $this->funded_amount,
                'remaining' => $this->remaining_funding,
            ],
            'terms' => [
                'interest_rate' => (float) $this->interest_rate,
                'loan_term_days' => $this->loan_term_days,
                'repayment_date' => $this->repayment_date?->toDateString(),
                'total_repayment' => (float) $this->total_repayment,
            ],
            'funding_progress' => [
                'percentage' => $this->funding_progress,
                'fully_funded' => $this->funding_progress >= 100,
            ],
            'risk_score' => (float) $this->risk_score,
            'borrower' => [
                'trust_score' => (int) $this->borrower?->trust_score,
                'trust_tier' => $this->borrower?->trustTier,
            ],
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
