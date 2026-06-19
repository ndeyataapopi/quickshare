@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <div class="row page-titles">
        <div class="col-md-5 align-self-center"><h4 class="text-themecolor">My Earnings</h4></div>
        <div class="col-md-7 align-self-center text-right">
            <ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li><li class="breadcrumb-item active">My Earnings</li></ol>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="text-uppercase mb-1">Total Earnings</h6>
                    <h3 class="mb-0">N$ {{ number_format($totalEarnings ?? auth()->user()->fundingTransactions()->sum('actual_return') - auth()->user()->fundingTransactions()->sum('amount')) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="text-uppercase mb-1">Total Invested</h6>
                    <h3 class="mb-0">N$ {{ number_format($totalInvested ?? auth()->user()->fundingTransactions()->sum('amount')) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="text-uppercase mb-1">Active Investments</h6>
                    <h3 class="mb-0">{{ $activeCount ?? auth()->user()->fundingTransactions()->whereHas('loan', fn($q) => $q->where('status','active'))->count() }}</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-3">Earnings Breakdown</h5>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th>
                                    <th>Loan Reference</th>
                                    <th>Invested</th>
                                    <th>Interest Rate</th>
                                    <th>Expected Return</th>
                                    <th>Actual Return</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                            @php $earnings = auth()->user()->fundingTransactions()->with('loan')->where('status','confirmed')->latest()->paginate(20); @endphp
                            @forelse($earnings as $e)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $e->loan->reference ?? '#'.$e->loan_id }}</td>
                                <td>N$ {{ number_format($e->amount) }}</td>
                                <td>{{ $e->interest_rate ?? '-' }}%</td>
                                <td>N$ {{ number_format($e->expected_return ?? 0) }}</td>
                                <td class="text-success font-weight-bold">N$ {{ number_format($e->actual_return ?? 0) }}</td>
                                <td><span class="badge badge-success">Received</span></td>
                                <td>{{ $e->created_at->format('M j, Y') }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="8" class="text-center py-5">
                                <i class="mdi mdi-cash-multiple text-muted" style="font-size:48px;"></i>
                                <h5 class="mt-3 text-muted">No Earnings Yet</h5>
                                <p class="text-muted">Fund loans to start earning interest.</p>
                                <a href="{{ route('client.marketplace.index') }}" class="btn btn-primary btn-sm mt-2">Browse Marketplace</a>
                            </td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $earnings->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
