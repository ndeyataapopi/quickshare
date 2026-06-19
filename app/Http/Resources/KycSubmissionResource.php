<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KycSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'user' => new UserResource($this->whenLoaded('user')),
            'reviewer' => new UserResource($this->whenLoaded('reviewer')),
            'documents' => KycDocumentResource::collection($this->whenLoaded('documents')),
            'missing_documents' => $this->getMissingDocuments(),
            'has_all_documents' => $this->hasAllRequiredDocuments(),
            'rejection_reason' => $this->when(
                ! is_null($this->rejection_reason),
                $this->rejection_reason
            ),
            'admin_notes' => $this->when(
                $request->user()?->hasRole('admin'),
                $this->admin_notes
            ),
            'timestamps' => [
                'submitted_at' => $this->submitted_at?->toIso8601String(),
                'reviewed_at' => $this->reviewed_at?->toIso8601String(),
                'created_at' => $this->created_at->toIso8601String(),
                'updated_at' => $this->updated_at->toIso8601String(),
            ],
        ];
    }
}
