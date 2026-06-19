<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = [
            'interest_rate'        => $this->getSetting('loans.interest_rate', 30.0),
            'platform_fee_percent' => $this->getSetting('loans.platform_fee_percent', 5.0),
            'min_amount'           => $this->getSetting('loans.min_amount', 1000),
            'max_amount'           => $this->getSetting('loans.max_amount', 50000),
            'min_term_days'        => $this->getSetting('loans.min_term_days', 30),
            'max_term_days'        => $this->getSetting('loans.max_term_days', 365),
            'min_funding_amount'   => $this->getSetting('loans.min_funding_amount', 500),
            'max_active_loans'     => $this->getSetting('loans.max_active_loans', 3),
            'currency'             => $this->getSetting('loans.currency', 'NAD'),
            'currency_symbol'      => $this->getSetting('loans.currency_symbol', 'N$'),
        ];

        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'interest_rate'        => 'required|numeric|min:0|max:100',
            'platform_fee_percent' => 'required|numeric|min:0|max:50',
            'min_amount'           => 'required|numeric|min:100|max:100000',
            'max_amount'           => 'required|numeric|min:1000|max:1000000',
            'min_term_days'        => 'required|integer|min:1|max:365',
            'max_term_days'        => 'required|integer|min:30|max:1825',
            'min_funding_amount'   => 'required|numeric|min:100|max:10000',
            'max_active_loans'     => 'required|integer|min:1|max:10',
            'currency'             => 'required|string|size:3',
            'currency_symbol'      => 'required|string|max:5',
        ]);

        foreach ($validated as $key => $value) {
            $this->setSetting('loans.' . $key, $value);
        }

        return redirect()->route('admin.settings.index')
            ->with('success', 'Settings updated successfully.');
    }

    private function getSetting($key, $default = null)
    {
        $setting = Setting::where('key', $key)->first();
        return $setting ? $setting->value : config($key, $default);
    }

    private function setSetting($key, $value)
    {
        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}
