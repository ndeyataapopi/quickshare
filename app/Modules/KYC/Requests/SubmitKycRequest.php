<?php

namespace App\Modules\KYC\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitKycRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSize = 10 * 1024; // 10MB in kilobytes

        return [
            'national_id_front' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', "max:{$maxSize}"],
            'national_id_back' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', "max:{$maxSize}"],
            'payslip' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', "max:{$maxSize}"],
            'bank_statement' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', "max:{$maxSize}"],
        ];
    }

    public function messages(): array
    {
        return [
            'national_id_front.required' => 'National ID front image is required.',
            'national_id_back.required' => 'National ID back image is required.',
            'payslip.required' => 'Payslip document is required.',
            'bank_statement.required' => '3-month bank statement is required.',
            '*.mimes' => 'Only JPG, PNG, WebP, and PDF files are allowed.',
            '*.max' => 'File must not exceed 10MB.',
        ];
    }
}
