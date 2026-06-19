<?php

namespace App\Modules\Loans\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'approved_amount' => ['sometimes', 'numeric', 'min:500'],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
