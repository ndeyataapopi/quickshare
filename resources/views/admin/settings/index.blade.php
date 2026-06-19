@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0"><i class="mdi mdi-cog mr-2"></i>Platform Settings</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">Settings</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    @if(session('success'))
        <div class="alert alert-success"><i class="mdi mdi-check-circle mr-2"></i>{{ session('success') }}</div>
    @endif
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-4">Loan Configuration</h5>
                    <form method="POST" action="{{ route('admin.settings.update') }}">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Currency Code</label>
                                    <input type="text" name="currency" class="form-control" value="{{ $settings['currency'] }}" maxlength="3" required>
                                    <small class="text-muted">3-letter currency code (e.g., NAD, USD)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Currency Symbol</label>
                                    <input type="text" name="currency_symbol" class="form-control" value="{{ $settings['currency_symbol'] }}" maxlength="5" required>
                                    <small class="text-muted">Currency symbol (e.g., N$, $)</small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Interest Rate (%)</label>
                                    <input type="number" name="interest_rate" class="form-control" value="{{ $settings['interest_rate'] }}" step="0.1" min="0" max="100" required>
                                    <small class="text-muted">Annual interest rate percentage</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Platform Fee (%)</label>
                                    <input type="number" name="platform_fee_percent" class="form-control" value="{{ $settings['platform_fee_percent'] }}" step="0.1" min="0" max="50" required>
                                    <small class="text-muted">Platform service fee percentage</small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Min Loan Amount</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">{{ $settings['currency_symbol'] }}</span>
                                        </div>
                                        <input type="number" name="min_amount" class="form-control" value="{{ $settings['min_amount'] }}" min="100" max="100000" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Max Loan Amount</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">{{ $settings['currency_symbol'] }}</span>
                                        </div>
                                        <input type="number" name="max_amount" class="form-control" value="{{ $settings['max_amount'] }}" min="1000" max="1000000" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Min Loan Term (days)</label>
                                    <input type="number" name="min_term_days" class="form-control" value="{{ $settings['min_term_days'] }}" min="1" max="365" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Max Loan Term (days)</label>
                                    <input type="number" name="max_term_days" class="form-control" value="{{ $settings['max_term_days'] }}" min="30" max="1825" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Min Funding Amount</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">{{ $settings['currency_symbol'] }}</span>
                                        </div>
                                        <input type="number" name="min_funding_amount" class="form-control" value="{{ $settings['min_funding_amount'] }}" min="100" max="10000" required>
                                    </div>
                                    <small class="text-muted">Minimum amount per lender</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Max Active Loans</label>
                                    <input type="number" name="max_active_loans" class="form-control" value="{{ $settings['max_active_loans'] }}" min="1" max="10" required>
                                    <small class="text-muted">Maximum per borrower</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="mdi mdi-content-save mr-2"></i>Save Settings
                            </button>
                            <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary ml-2">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
