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
                <h2 class="font-bold">N$ {{ number_format($earningsSummary['total_invested'] ?? 0) }}</h2>
                <i class="mdi mdi-cash-multiple text-primary float-right" style="font-size:40px;opacity:.3"></i>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card"><div class="card-body">
                <h5 class="card-title text-muted text-uppercase small">Total Earnings</h5>
                <h2 class="font-bold">N$ {{ number_format($earningsSummary['total_earnings'] ?? 0) }}</h2>
                <i class="mdi mdi-trending-up text-success float-right" style="font-size:40px;opacity:.3"></i>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card"><div class="card-body">
                <h5 class="card-title text-muted text-uppercase small">Active Investments</h5>
                <h2 class="font-bold">{{ $earningsSummary['active_count'] ?? 0 }}</h2>
                <i class="mdi mdi-bank text-info float-right" style="font-size:40px;opacity:.3"></i>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card"><div class="card-body">
                <h5 class="card-title text-muted text-uppercase small">Average Return</h5>
                <h2 class="font-bold">{{ number_format($earningsSummary['roi'] ?? 0, 1) }}%</h2>
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
                        $sc = ['confirmed'=>'success','pending'=>'warning','cancelled'=>'danger'];
                    @endphp
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Status</th><th>Count</th><th>Total Amount</th></tr></thead>
                            <tbody>
                            @foreach($investmentsByStatus as $row)
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
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Loan</th><th>Invested</th><th>Expected Return</th></tr></thead>
                            <tbody>
                            @forelse($topInvestments as $inv)
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
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Period</th><th>Investments</th><th>Total Amount</th></tr></thead>
                            <tbody>
                            @forelse($monthlyActivity as $m)
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
