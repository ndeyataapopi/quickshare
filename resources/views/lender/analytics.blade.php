@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <div class="row page-titles">
        <div class="col-md-5 align-self-center"><h4 class="text-themecolor">Analytics</h4></div>
        <div class="col-md-7 align-self-center text-right">
            <ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li><li class="breadcrumb-item active">Analytics</li></ol>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3">
            <div class="card"><div class="card-body">
                <h5 class="card-title text-muted text-uppercase small">Total Invested</h5>
                <h2 class="font-bold">N$ {{ number_format(auth()->user()->fundingTransactions()->sum('amount')) }}</h2>
                <i class="mdi mdi-cash-multiple text-primary float-right" style="font-size:40px;opacity:.3"></i>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card"><div class="card-body">
                <h5 class="card-title text-muted text-uppercase small">Total Earnings</h5>
                <h2 class="font-bold">N$ {{ number_format(auth()->user()->fundingTransactions()->sum('actual_return') - auth()->user()->fundingTransactions()->sum('amount')) }}</h2>
                <i class="mdi mdi-trending-up text-success float-right" style="font-size:40px;opacity:.3"></i>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card"><div class="card-body">
                <h5 class="card-title text-muted text-uppercase small">Active Investments</h5>
                <h2 class="font-bold">{{ auth()->user()->fundingTransactions()->whereHas('loan', fn($q) => $q->where('status','active'))->count() }}</h2>
                <i class="mdi mdi-bank text-info float-right" style="font-size:40px;opacity:.3"></i>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card"><div class="card-body">
                <h5 class="card-title text-muted text-uppercase small">Average Return</h5>
                <h2 class="font-bold">{{ number_format(auth()->user()->fundingTransactions()->avg('interest_rate') ?? 0, 1) }}%</h2>
                <i class="mdi mdi-percent text-warning float-right" style="font-size:40px;opacity:.3"></i>
            </div></div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Investments by Status</h5>
                    @php
                        $byStatus = auth()->user()->fundingTransactions()->selectRaw('status, count(*) as total, sum(amount) as total_amount')->groupBy('status')->get();
                        $sc = ['confirmed'=>'success','pending'=>'warning','cancelled'=>'danger'];
                    @endphp
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Status</th><th>Count</th><th>Total Amount</th></tr></thead>
                            <tbody>
                            @foreach($byStatus as $row)
                            <tr>
                                <td><span class="badge badge-{{ $sc[$row->status] ?? 'secondary' }}">{{ ucfirst($row->status) }}</span></td>
                                <td>{{ $row->total }}</td>
                                <td>N$ {{ number_format($row->total_amount) }}</td>
                            </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Top Investments</h5>
                    @php $topInv = auth()->user()->fundingTransactions()->with('loan')->orderByDesc('amount')->take(5)->get(); @endphp
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Loan</th><th>Invested</th><th>Expected Return</th></tr></thead>
                            <tbody>
                            @forelse($topInv as $inv)
                            <tr>
                                <td>{{ $inv->loan->reference ?? '#'.$inv->loan_id }}</td>
                                <td>N$ {{ number_format($inv->amount) }}</td>
                                <td class="text-success">N$ {{ number_format($inv->expected_return ?? 0) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-center text-muted">No investments yet.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Monthly Activity</h5>
                    @php
                        $monthly = auth()->user()->fundingTransactions()
                            ->selectRaw('YEAR(created_at) as yr, MONTH(created_at) as mo, count(*) as cnt, sum(amount) as total')
                            ->groupByRaw('YEAR(created_at), MONTH(created_at)')
                            ->orderByRaw('YEAR(created_at) DESC, MONTH(created_at) DESC')
                            ->take(6)->get();
                    @endphp
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Period</th><th>Investments</th><th>Total Amount</th></tr></thead>
                            <tbody>
                            @forelse($monthly as $m)
                            <tr>
                                <td>{{ \Carbon\Carbon::create($m->yr, $m->mo)->format('F Y') }}</td>
                                <td>{{ $m->cnt }}</td>
                                <td>N$ {{ number_format($m->total) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-center text-muted">No activity yet.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
