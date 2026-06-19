<?php

namespace App\Modules\Loans\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $minAmount   = (int) config('loans.min_amount', 500);
        $maxAmount   = (int) config('loans.max_amount', 1500);
        $minTermDays = (int) config('loans.min_term_days', 7);
        $maxTermDays = (int) config('loans.max_term_days', 30);

        return [
            'requested_amount' => ['required', 'numeric', "min:{$minAmount}", "max:{$maxAmount}"],
            'loan_term_days'   => ['required', 'integer', "min:{$minTermDays}", "max:{$maxTermDays}"],
            'purpose'          => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        $currency    = config('loans.currency_symbol', 'N$');
        $minAmount   = (int) config('loans.min_amount', 500);
        $maxAmount   = (int) config('loans.max_amount', 1500);
        $minTermDays = (int) config('loans.min_term_days', 7);
        $maxTermDays = (int) config('loans.max_term_days', 30);

        return [
            'requested_amount.min' => "Minimum loan amount is {$currency} {$minAmount}.",
            'requested_amount.max' => "Maximum loan amount is {$currency} {$maxAmount}.",
            'loan_term_days.min'   => "Minimum loan term is {$minTermDays} days.",
            'loan_term_days.max'   => "Maximum loan term is {$maxTermDays} days.",
        ];
    }
}
