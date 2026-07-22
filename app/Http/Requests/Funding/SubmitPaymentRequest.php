<?php

namespace App\Http\Requests\Funding;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $method = $this->input('payment_method');

        return [
            'payment_method' => ['required', 'in:eft,mobile_wallet,cash_deposit'],
            'payment_method_detail' => [
                'required',
                'string',
                function ($attribute, $value, $fail) use ($method) {
                    if ($method === 'eft' && ! array_key_exists($value, config('payments.banks', []))) {
                        $fail('Please select a valid bank.');
                    }
                    if ($method === 'mobile_wallet' && ! array_key_exists($value, config('payments.wallets', []))) {
                        $fail('Please select a valid mobile wallet.');
                    }
                },
            ],
            'payment_reference' => ['required', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'transaction_number' => ['nullable', 'string', 'max:255'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'proof_of_payment' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payment_confirmation' => ['required', 'accepted'],
        ];
    }

    public function attributes(): array
    {
        return [
            'payment_method' => 'payment method',
            'payment_method_detail' => 'bank or wallet',
            'proof_of_payment' => 'proof of payment',
            'payment_confirmation' => 'payment confirmation',
        ];
    }
}
