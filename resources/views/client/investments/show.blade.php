@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">Investment Details</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('client.investments.index') }}">My Investments</a></li>
                    <li class="breadcrumb-item active">Details</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-4">Investment Details</h5>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Amount Invested</div><div class="col-sm-8 font-weight-bold">N$ {{ number_format($investment->amount, 2) }}</div></div>
                    @if($investment->loan)
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Loan</div><div class="col-sm-8"><a href="{{ route('client.loans.show', $investment->loan) }}">{{ $investment->loan->reference }}</a></div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Loan Status</div><div class="col-sm-8"><span class="badge badge-info">{{ ucwords(str_replace('_',' ',$investment->loan->status)) }}</span></div></div>
                    @endif
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Interest Rate</div><div class="col-sm-8">{{ $investment->interest_rate ?? '-' }}%</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Expected Return</div><div class="col-sm-8 text-success font-weight-bold">N$ {{ number_format($investment->expected_return, 2) }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Actual Return</div><div class="col-sm-8 text-success">N$ {{ number_format($investment->actual_return ?? 0, 2) }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Expected Profit</div><div class="col-sm-8">N$ {{ number_format($investment->expected_return - $investment->amount, 2) }}</div></div>
                    <div class="row mb-3">
                        <div class="col-sm-4 text-muted">Status</div>
                        <div class="col-sm-8">
                            @php $sc=['pending'=>'warning','active'=>'primary','completed'=>'success','cancelled'=>'secondary']; @endphp
                            <span class="badge badge-{{ $sc[$investment->status] ?? 'secondary' }}">{{ ucfirst($investment->status) }}</span>
                        </div>
                    </div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Invested On</div><div class="col-sm-8">{{ $investment->created_at->format('M j, Y') }}</div></div>
                    @if($investment->completed_at)
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Completed On</div><div class="col-sm-8">{{ $investment->completed_at->format('M j, Y') }}</div></div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Quick Actions</h5>
                    <a href="{{ route('client.investments.index') }}" class="btn btn-outline-secondary btn-block mb-2"><i class="mdi mdi-arrow-left"></i> Back to Investments</a>
                    <a href="{{ route('client.marketplace.index') }}" class="btn btn-outline-primary btn-block"><i class="mdi mdi-tune"></i> Browse Marketplace</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
