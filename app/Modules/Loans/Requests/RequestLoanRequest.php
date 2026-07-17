<?php

namespace App\Modules\Loans\Requests;

use App\Modules\Loans\Services\TrustTierService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $minimumAmount = (float) config('loans.min_amount');
        $tier = app(TrustTierService::class)->forScore((float) $this->user()->trust_score);

        return [
            'requested_amount' => ['required', 'numeric', "min:{$minimumAmount}", "max:{$tier['maximum_loan']}"],
            'loan_term_days' => ['required', 'integer', Rule::in($tier['allowed_durations'])],
            'purpose' => ['nullable', 'string', 'max:500'],
            'agreement_read' => ['required', 'accepted'],
            'agreement_terms' => ['required', 'accepted'],
            'electronic_documents' => ['required', 'accepted'],
            'agreement_version' => ['required', 'string', Rule::in([(string) config('loan.agreement.version')])],
        ];
    }

    public function messages(): array
    {
        $currency = config('loans.currency_symbol', 'N$');
        $minimumAmount = (float) config('loans.min_amount');
        $tier = app(TrustTierService::class)->forScore((float) $this->user()->trust_score);

        return [
            'requested_amount.min' => "Minimum loan amount is {$currency} {$minimumAmount}.",
            'requested_amount.max' => "Maximum loan amount is {$currency} {$tier['maximum_loan']}.",
            'loan_term_days.in' => 'Allowed loan terms are: '.implode(', ', $tier['allowed_durations']).' days.',
            'agreement_version.in' => 'The loan agreement has changed. Please review the current agreement before submitting.',
        ];
    }
}
