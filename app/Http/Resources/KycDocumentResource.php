<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KycDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_type' => $this->document_type,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'status' => $this->status,
            'scan_passed' => $this->scan_passed,
            'scanned_at' => $this->scanned_at?->toIso8601String(),
            'rejection_reason' => $this->when(
                ! is_null($this->rejection_reason),
                $this->rejection_reason
            ),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
