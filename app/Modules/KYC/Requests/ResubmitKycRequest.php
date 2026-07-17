<?php

namespace App\Modules\KYC\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResubmitKycRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSize = 10 * 1024;

        return [
            'national_id' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,webp,pdf', "max:{$maxSize}"],
            'selfie' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,webp', "max:{$maxSize}"],
            'payslip' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,webp,pdf', "max:{$maxSize}"],
            'bank_statement' => ['sometimes', 'file', 'mimes:jpg,jpeg,png,webp,pdf', "max:{$maxSize}"],
        ];
    }

    public function messages(): array
    {
        return [
            '*.mimes' => 'Only JPG, PNG, WebP, and PDF files are allowed.',
            '*.max' => 'File must not exceed 10MB.',
        ];
    }
}
