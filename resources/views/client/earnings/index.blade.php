@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0"><i class="mdi mdi-cash-multiple mr-2"></i>My Earnings</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">My Earnings</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="text-uppercase mb-1">Total Earnings</h6>
                    <h3 class="mb-0">N\$ {{ number_format($totalEarnings ?? 0) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="text-uppercase mb-1">Total Invested</h6>
                    <h3 class="mb-0">N\$ {{ number_format($totalInvested ?? 0) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="text-uppercase mb-1">Active Investments</h6>
                    <h3 class="mb-0">{{ $activeCount ?? 0 }}</h3>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Earnings Breakdown</h5>
                    @if(isset($earnings) && $earnings->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="thead-light">
                                <tr><th>#</th><th>Loan</th><th>Invested</th><th>Interest Rate</th><th>Earned</th><th>Date</th></tr>
                            </thead>
                            <tbody>
                                @foreach($earnings as $e)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $e->loan ? $e->loan->reference : '#' . $e->loan_id }}</td>
                                    <td>N$ {{ number_format($e->amount, 2) }}</td>
                                    <td>{{ $e->interest_rate ?? '-' }}%</td>
                                    <td class="text-success font-weight-bold">N$ {{ number_format($e->actual_return ?? 0, 2) }}</td>
                                    <td>{{ $e->completed_at ? $e->completed_at->format('M j, Y') : $e->created_at->format('M j, Y') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="text-center py-5">
                        <i class="mdi mdi-cash-multiple text-muted" style="font-size:64px;"></i>
                        <h5 class="mt-3 text-muted">No Earnings Yet</h5>
                        <p class="text-muted">Fund loans to start earning interest.</p>
                        <a href="{{ route('client.marketplace.index') }}" class="btn btn-primary">Browse Marketplace</a>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
