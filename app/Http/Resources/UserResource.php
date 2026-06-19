<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->first_name . ' ' . $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'status' => $this->status,
            'roles' => $this->getRoleNames(),
            'trust_score' => [
                'score' => (int) $this->trust_score,
                'tier' => $this->trustTier,
                'risk_level' => $this->riskLevel,
            ],
            'verification' => [
                'email_verified' => ! is_null($this->email_verified_at),
                'phone_verified' => ! is_null($this->phone_verified_at),
                'email_verified_at' => $this->email_verified_at?->toIso8601String(),
                'phone_verified_at' => $this->phone_verified_at?->toIso8601String(),
            ],
            'referral_code' => $this->referral_code,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
