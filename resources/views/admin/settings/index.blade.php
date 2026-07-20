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
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="alert alert-info">
                <i class="mdi mdi-information mr-2"></i>
                These values are loaded from <code>config/loans.php</code> and your environment variables. Update them via your deployment configuration.
            </div>
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-4">Loan Configuration</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Currency Code</label>
                                <p class="form-control-plaintext border p-2 rounded bg-light">{{ $settings['currency'] }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Currency Symbol</label>
                                <p class="form-control-plaintext border p-2 rounded bg-light">{{ $settings['currency_symbol'] }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Interest Rate (%)</label>
                                <p class="form-control-plaintext border p-2 rounded bg-light">{{ number_format($settings['interest_rate'], 2) }}%</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Platform Fee (%)</label>
                                <p class="form-control-plaintext border p-2 rounded bg-light">{{ number_format($settings['platform_fee_percent'], 2) }}%</p>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Min Loan Amount</label>
                                <p class="form-control-plaintext border p-2 rounded bg-light">{{ $settings['currency_symbol'] }} {{ number_format($settings['min_amount'], 2) }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Max Loan Amount</label>
                                <p class="form-control-plaintext border p-2 rounded bg-light">{{ $settings['currency_symbol'] }} {{ number_format($settings['max_amount'], 2) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Min Loan Term (days)</label>
                                <p class="form-control-plaintext border p-2 rounded bg-light">{{ $settings['min_term_days'] }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Max Loan Term (days)</label>
                                <p class="form-control-plaintext border p-2 rounded bg-light">{{ $settings['max_term_days'] }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Min Funding Amount</label>
                                <p class="form-control-plaintext border p-2 rounded bg-light">{{ $settings['currency_symbol'] }} {{ number_format($settings['min_funding_amount'], 2) }}</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Max Active Loans</label>
                                <p class="form-control-plaintext border p-2 rounded bg-light">{{ $settings['max_active_loans'] }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mt-4">
                        <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">Back to Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
