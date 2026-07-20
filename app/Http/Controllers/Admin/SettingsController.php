<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = [
            'interest_rate'        => config('loans.interest_rate', 30.0),
            'platform_fee_percent' => config('loans.platform_fee_percent', 5.0),
            'min_amount'           => config('loans.min_amount', 500.0),
            'max_amount'           => config('loans.max_amount', 1500.0),
            'min_term_days'        => config('loans.min_term_days', 7),
            'max_term_days'        => config('loans.max_term_days', 30),
            'min_funding_amount'   => config('loans.min_funding_amount', 500.0),
            'max_active_loans'     => config('loans.max_active_loans', 3),
            'currency'             => config('loans.currency', 'NAD'),
            'currency_symbol'      => config('loans.currency_symbol', 'N$'),
        ];

        return view('admin.settings.index', compact('settings'));
    }
}
