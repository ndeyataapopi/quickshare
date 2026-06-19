@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">Funding Details</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('client.investments.index') }}">Investments</a></li>
                    <li class="breadcrumb-item active">Funding #{{ $transaction->transaction_reference }}</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-4">Investment Details</h5>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Reference</div><div class="col-sm-8 font-weight-bold">{{ $transaction->transaction_reference }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Loan</div><div class="col-sm-8">{{ $transaction->loan->reference }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Borrower</div><div class="col-sm-8">{{ $transaction->loan->borrower->first_name }} {{ $transaction->loan->borrower->last_name }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Amount Invested</div><div class="col-sm-8 font-weight-bold">N$ {{ number_format($transaction->amount, 2) }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Interest Rate</div><div class="col-sm-8">{{ $transaction->interest_rate }}%</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Expected Return</div><div class="col-sm-8 text-success font-weight-bold">N$ {{ number_format($transaction->expected_return, 2) }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Expected Profit</div><div class="col-sm-8 text-success">N$ {{ number_format($transaction->expected_return - $transaction->amount, 2) }}</div></div>
                    <div class="row mb-2">
                        <div class="col-sm-4 text-muted">Status</div>
                        <div class="col-sm-8">
                            @php $sc=['pending'=>'warning','confirmed'=>'success','cancelled'=>'danger','refunded'=>'secondary']; @endphp
                            <span class="badge badge-{{ $sc[$transaction->status] ?? 'secondary' }}">{{ ucfirst($transaction->status) }}</span>
                        </div>
                    </div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Date</div><div class="col-sm-8">{{ $transaction->created_at->format('M j, Y g:i A') }}</div></div>
                    @if($transaction->confirmed_at)
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Confirmed At</div><div class="col-sm-8">{{ $transaction->confirmed_at->format('M j, Y g:i A') }}</div></div>
                    @endif
                    @if($transaction->notes)
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Notes</div><div class="col-sm-8 small">{{ $transaction->notes }}</div></div>
                    @endif
                </div>
            </div>

            @if($transaction->status === 'pending')
            <div class="card border-warning">
                <div class="card-body">
                    <h6 class="text-uppercase font-weight-bold text-warning mb-2">
                        <i class="mdi mdi-alert-circle mr-1"></i> Payment Pending
                    </h6>
                    <p class="text-muted small mb-3">Your funding pledge is reserved. Complete payment to confirm your investment.</p>
                    <a href="{{ route('client.funding.payment', $transaction) }}" class="btn btn-primary">
                        <i class="mdi mdi-bank-transfer mr-1"></i> Complete Payment
                    </a>
                </div>
            </div>
            @endif

            <a href="{{ route('client.investments.index') }}" class="btn btn-outline-secondary">
                <i class="mdi mdi-arrow-left mr-1"></i> Back to Investments
            </a>
        </div>
    </div>
</div>
@endsection
