<?php

namespace App\Modules\KYC\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewKycRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:approve,reject'],
            'reason' => ['required_if:action,reject', 'nullable', 'string', 'max:1000'],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
            'allow_resubmission' => ['sometimes', 'boolean'],
            'document_rejections' => ['sometimes', 'array'],
            'document_rejections.*' => ['string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.in' => 'Action must be either approve or reject.',
            'reason.required_if' => 'A rejection reason is required when rejecting.',
        ];
    }
}
