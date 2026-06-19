@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <div class="row page-titles">
        <div class="col-md-5 align-self-center"><h4 class="text-themecolor">Request a Loan</h4></div>
        <div class="col-md-7 align-self-center text-right">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('client.loans.index') }}">My Loans</a></li>
                <li class="breadcrumb-item active">New Loan</li>
            </ol>
        </div>
    </div>
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-4">Loan Application</h5>
                    @php
                        $currency = config('loans.currency_symbol','N$');
                        $minAmount = config('loans.min_amount',500);
                        $maxAmount = config('loans.max_amount',25000);
                        $minTerm = config('loans.min_term_days',7);
                        $maxTerm = config('loans.max_term_days',30);
                        $baseRate = config('loans.interest_rate',30.00);
                    @endphp
                    <form method="POST" action="{{ route('client.loans.store') }}">
                        @csrf
                        <div class="form-group">
                            <label>Loan Amount ({{ $currency }})</label>
                            <input type="number" name="amount" id="amount" class="form-control @error('amount') is-invalid @enderror"
                                min="{{ $minAmount }}" max="{{ $maxAmount }}" value="{{ old('amount') }}"
                                placeholder="{{ $minAmount }}" required>
                            @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <small class="text-muted">Min: {{ $currency }}{{ number_format($minAmount) }} | Max: {{ $currency }}{{ number_format($maxAmount) }}</small>
                        </div>

                        <div class="form-group">
                            <label>Repayment Period (Days)</label>
                            <select name="loan_term_days" id="term" class="form-control @error('loan_term_days') is-invalid @enderror" required>
                                <option value="">Select period</option>
                                @foreach([7,14,21,30] as $d)
                                <option value="{{ $d }}" {{ old('loan_term_days')==$d ? 'selected' : '' }}>{{ $d }} Days</option>
                                @endforeach
                            </select>
                            @error('loan_term_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-group">
                            <label>Description <small class="text-muted">(optional)</small></label>
                            <textarea name="description" rows="3" class="form-control" placeholder="How do you plan to use the funds?">{{ old('description') }}</textarea>
                        </div>

                        @if(auth()->user()->trust_score < 50)
                        <div class="alert alert-warning"><i class="mdi mdi-alert mr-1"></i> Your trust score is below 50. Improving it will unlock better rates.</div>
                        @endif

                        <div class="card bg-light mb-4">
                            <div class="card-body">
                                <h6 class="font-weight-bold mb-3">Loan Preview</h6>
                                <div class="row text-center">
                                    <div class="col-6 col-md-3 mb-2"><div class="text-muted small">Interest Rate</div><div id="prev_rate" class="font-weight-bold">{{ $baseRate }}%</div></div>
                                    <div class="col-6 col-md-3 mb-2"><div class="text-muted small">Total Interest</div><div id="prev_interest" class="font-weight-bold">{{ $currency }} 0</div></div>
                                    <div class="col-6 col-md-3 mb-2"><div class="text-muted small">Platform Fee</div><div id="prev_fee" class="font-weight-bold">{{ $currency }} 0</div></div>
                                    <div class="col-6 col-md-3 mb-2"><div class="text-muted small">Total Repayment</div><div id="prev_total" class="font-weight-bold text-primary">{{ $currency }} 0</div></div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="mdi mdi-send mr-1"></i> Submit Loan Request
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Affordability Check</h5>
                    <div class="form-group">
                        <label>Monthly Income (N$)</label>
                        <input type="number" id="income" class="form-control" placeholder="e.g. 15000">
                    </div>
                    <div class="form-group">
                        <label>Monthly Expenses (N$)</label>
                        <input type="number" id="expenses" class="form-control" placeholder="e.g. 8000">
                    </div>
                    <div class="alert alert-info mt-3 mb-0">
                        Max affordable: <strong id="max_loan">{{ $currency }} 0</strong>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Your Trust Score</h5>
                    <div class="d-flex align-items-center">
                        <span class="display-4 font-bold text-primary mr-2">{{ auth()->user()->trust_score ?? 0 }}</span>
                        <span class="text-muted">/100</span>
                    </div>
                    <div class="progress mt-2" style="height:8px">
                        <div class="progress-bar bg-primary" style="width:{{ auth()->user()->trust_score ?? 0 }}%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const amountInput = document.getElementById('amount');
    const termSelect = document.getElementById('term');
    const trustScore = {{ auth()->user()->trust_score ?? 0 }};
    const baseRate = {{ $baseRate }};
    const currency = '{{ $currency }}';

    function rate(score) {
        if (score >= 85) return baseRate - 5;
        if (score >= 70) return baseRate - 3;
        if (score >= 50) return baseRate - 1;
        return baseRate;
    }

    function fmt(n) { return currency + ' ' + n.toLocaleString('en',{minimumFractionDigits:2,maximumFractionDigits:2}); }

    function updatePreview() {
        const amount = parseFloat(amountInput.value) || 0;
        const days = parseInt(termSelect.value) || 0;
        const r = rate(trustScore);
        const interest = amount * (r / 100) * (days / 365);
        const fee = amount * 0.02;
        const total = amount + interest + fee;
        document.getElementById('prev_rate').textContent = r.toFixed(2) + '%';
        document.getElementById('prev_interest').textContent = fmt(interest);
        document.getElementById('prev_fee').textContent = fmt(fee);
        document.getElementById('prev_total').textContent = fmt(total);
    }

    amountInput.addEventListener('input', updatePreview);
    termSelect.addEventListener('change', updatePreview);

    document.getElementById('income').addEventListener('input', updateMax);
    document.getElementById('expenses').addEventListener('input', updateMax);

    function updateMax() {
        const inc = parseFloat(document.getElementById('income').value) || 0;
        const exp = parseFloat(document.getElementById('expenses').value) || 0;
        document.getElementById('max_loan').textContent = fmt((inc - exp) * 6);
    }
});
</script>
@endpush
