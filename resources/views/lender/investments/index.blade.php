@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <div class="row page-titles">
        <div class="col-md-5 align-self-center"><h4 class="text-themecolor">My Investments</h4></div>
        <div class="col-md-7 align-self-center text-right">
            <ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li><li class="breadcrumb-item active">My Investments</li></ol>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card"><div class="card-body">
                <h5 class="card-title text-muted text-uppercase small">Total Invested</h5>
                <h2 class="font-bold">N$ {{ number_format(auth()->user()->fundingTransactions()->sum('amount')) }}</h2>
                <i class="mdi mdi-cash text-primary float-right" style="font-size:40px;opacity:.3"></i>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card"><div class="card-body">
                <h5 class="card-title text-muted text-uppercase small">Expected Returns</h5>
                <h2 class="font-bold">N$ {{ number_format(auth()->user()->fundingTransactions()->sum('expected_return')) }}</h2>
                <i class="mdi mdi-chart-line text-success float-right" style="font-size:40px;opacity:.3"></i>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card"><div class="card-body">
                <h5 class="card-title text-muted text-uppercase small">Active Loans</h5>
                <h2 class="font-bold">{{ auth()->user()->fundingTransactions()->whereHas('loan', fn($q) => $q->where('status','active'))->count() }}</h2>
                <i class="mdi mdi-bank text-info float-right" style="font-size:40px;opacity:.3"></i>
            </div></div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">Investment Portfolio</h5>
                        <a href="{{ route('client.marketplace.index') }}" class="btn btn-primary btn-sm"><i class="mdi mdi-plus mr-1"></i>Browse Marketplace</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="thead-light">
                                <tr>
                                    <th>Loan Reference</th>
                                    <th>Borrower</th>
                                    <th>Amount Invested</th>
                                    <th>Interest Rate</th>
                                    <th>Expected Return</th>
                                    <th>Actual Return</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                            @php $investments = auth()->user()->fundingTransactions()->with('loan.borrower')->latest()->paginate(20); @endphp
                            @forelse($investments as $inv)
                            <tr>
                                <td><a href="{{ route('client.investments.show', $inv) }}">{{ $inv->loan->reference ?? '#'.$inv->loan_id }}</a></td>
                                <td>{{ $inv->loan->borrower->first_name ?? '-' }} {{ $inv->loan->borrower->last_name ?? '' }}</td>
                                <td>N$ {{ number_format($inv->amount) }}</td>
                                <td>{{ $inv->interest_rate ?? '-' }}%</td>
                                <td>N$ {{ number_format($inv->expected_return ?? 0) }}</td>
                                <td class="text-success">N$ {{ number_format($inv->actual_return ?? 0) }}</td>
                                <td><span class="badge badge-{{ $inv->status==='confirmed' ? 'success' : ($inv->status==='pending' ? 'warning' : 'secondary') }}">{{ ucfirst($inv->status) }}</span></td>
                                <td>{{ $inv->created_at->format('M j, Y') }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="8" class="text-center py-5">
                                <i class="mdi mdi-trending-up text-muted" style="font-size:48px;"></i>
                                <h5 class="mt-3 text-muted">No Investments Yet</h5>
                                <a href="{{ route('client.marketplace.index') }}" class="btn btn-primary btn-sm mt-2">Browse Marketplace</a>
                            </td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $investments->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
