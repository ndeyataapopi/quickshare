<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = [
            'interest_rate'        => config('loan.fees.default_interest_rate', 30.0),
            'platform_fee_percent' => config('loan.fees.default_platform_fee_percent', 0.0),
            'min_amount'           => config('loan.loan_limits.min_amount', 500.0),
            'max_amount'           => config('loan.loan_limits.max_amount', 1500.0),
            'min_term_days'        => config('loan.loan_limits.min_term_days', 7),
            'max_term_days'        => config('loan.loan_limits.max_term_days', 30),
            'min_funding_amount'   => config('loan.marketplace.min_funding_amount', 500.0),
            'max_active_loans'     => config('loan.loan_limits.max_active_loans', 1),
            'currency'             => config('loan.general.currency', 'NAD'),
            'currency_symbol'      => config('loan.general.currency_symbol', 'N$'),
        ];

        return view('admin.settings.index', compact('settings'));
    }
}
