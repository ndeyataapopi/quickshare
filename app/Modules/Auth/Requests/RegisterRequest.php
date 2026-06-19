<?php

namespace App\Modules\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'referral_code' => ['required', 'string', 'size:8'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'national_id' => ['required', 'string', 'max:20', 'unique:users,national_id'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'date_of_birth' => ['required', 'date', 'before:-18 years'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['sometimes', 'string', 'in:borrower,lender,client,compliance_officer'],

            // Address
            'address' => ['required', 'array'],
            'address.country' => ['required', 'string', 'max:255'],
            'address.city' => ['required', 'string', 'max:255'],
            'address.suburb' => ['nullable', 'string', 'max:255'],
            'address.street' => ['required', 'string', 'max:255'],
            'address.house_number' => ['required', 'string', 'max:50'],

            // Source of Income
            'source_of_income' => ['required', 'array'],
            'source_of_income.profession' => ['required', 'string', 'in:employed,self-employed,unemployed'],
            'source_of_income.company_name' => ['nullable', 'required_unless:source_of_income.profession,unemployed', 'string', 'max:255'],
            'source_of_income.city' => ['nullable', 'required_unless:source_of_income.profession,unemployed', 'string', 'max:255'],
            'source_of_income.country' => ['nullable', 'required_unless:source_of_income.profession,unemployed', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'referral_code.required' => 'A referral code is required to register.',
            'referral_code.size' => 'Referral code must be exactly 8 characters.',
            'national_id.unique' => 'This national ID is already registered.',
            'email.unique' => 'This email address is already registered.',
            'phone.unique' => 'This phone number is already registered.',
            'date_of_birth.before' => 'You must be at least 18 years old to register.',
            'source_of_income.profession.in' => 'Profession must be employed, self-employed, or unemployed.',
            'source_of_income.company_name.required_unless' => 'Company/organisation name is required for employed users.',
        ];
    }
}
