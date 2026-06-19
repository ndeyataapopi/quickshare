@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">Complete Payment</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('client.investments.index') }}">Investments</a></li>
                    <li class="breadcrumb-item active">Complete Payment</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            <div class="card border-primary">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-1">Investment Summary</h5>
                    <div class="row mt-3">
                        <div class="col-6"><span class="text-muted">Reference</span><br><strong>{{ $transaction->transaction_reference }}</strong></div>
                        <div class="col-6"><span class="text-muted">Loan</span><br><strong>{{ $transaction->loan->reference }}</strong></div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-6"><span class="text-muted">Amount to Pay</span><br><strong class="text-primary" style="font-size:20px;">N$ {{ number_format($transaction->amount, 2) }}</strong></div>
                        <div class="col-6"><span class="text-muted">Expected Return</span><br><strong class="text-success">N$ {{ number_format($transaction->expected_return, 2) }}</strong></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Escrow Payment Instructions</h5>
                    <div class="alert alert-info">
                        <p class="mb-1"><i class="mdi mdi-information mr-1"></i> Transfer exactly <strong>N$ {{ number_format($transaction->amount, 2) }}</strong> to the QuickShare escrow account below.</p>
                        <p class="mb-0 small">Use your <strong>transaction reference as the payment reference</strong> so we can match your payment.</p>
                    </div>
                    <table class="table table-bordered mt-3">
                        <tbody>
                            <tr><td class="text-muted" width="40%">Bank</td><td class="font-weight-bold">First National Bank (FNB)</td></tr>
                            <tr><td class="text-muted">Account Name</td><td class="font-weight-bold">QuickShare (Pty) Ltd — Escrow</td></tr>
                            <tr><td class="text-muted">Account Number</td><td class="font-weight-bold">62XXXXXXXXXX</td></tr>
                            <tr><td class="text-muted">Branch Code</td><td class="font-weight-bold">281272</td></tr>
                            <tr><td class="text-muted">Reference</td><td><strong class="text-primary">{{ $transaction->transaction_reference }}</strong></td></tr>
                            <tr><td class="text-muted">Amount</td><td class="font-weight-bold text-primary">N$ {{ number_format($transaction->amount, 2) }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Confirm Your Payment</h5>
                    <form method="POST" action="{{ route('client.funding.payment.submit', $transaction) }}">
                        @csrf
                        <div class="form-group">
                            <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <select name="payment_method" class="form-control @error('payment_method') is-invalid @enderror" required>
                                <option value="">Select method</option>
                                <option value="eft" {{ old('payment_method') === 'eft' ? 'selected' : '' }}>EFT (Electronic Funds Transfer)</option>
                                <option value="instant_eft" {{ old('payment_method') === 'instant_eft' ? 'selected' : '' }}>Instant EFT</option>
                                <option value="bank_transfer" {{ old('payment_method') === 'bank_transfer' ? 'selected' : '' }}>Internet Bank Transfer</option>
                            </select>
                            @error('payment_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label">Payment Reference / Proof Reference <span class="text-danger">*</span></label>
                            <input type="text" name="payment_reference"
                                class="form-control @error('payment_reference') is-invalid @enderror"
                                value="{{ old('payment_reference', $transaction->transaction_reference) }}"
                                placeholder="Your bank transaction reference" required>
                            @error('payment_reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-success">
                            <i class="mdi mdi-check mr-1"></i> I Have Made the Payment
                        </button>
                        <a href="{{ route('client.funding.show', $transaction) }}" class="btn btn-secondary ml-2">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
