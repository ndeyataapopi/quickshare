@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <div class="row page-titles">
        <div class="col-md-5 align-self-center"><h4 class="text-themecolor">Lender Dashboard</h4></div>
        <div class="col-md-7 align-self-center text-right">
            <ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li><li class="breadcrumb-item active">Dashboard</li></ol>
        </div>
    </div>
    @if(session('success'))<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>@endif

    <div class="row">
        <div class="col-md-3">
            <div class="card"><div class="card-body">
                <h5 class="card-title text-muted text-uppercase small">Total Funded</h5>
                <h2 class="font-bold">{{ formatCurrencyShort(auth()->user()->fundingTransactions()->sum('amount')) }}</h2>
                <i class="mdi mdi-trending-up text-primary float-right" style="font-size:40px;opacity:.3"></i>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card"><div class="card-body">
                <h5 class="card-title text-muted text-uppercase small">Active Investments</h5>
                <h2 class="font-bold">{{ auth()->user()->fundingTransactions()->whereHas('loan', fn($q) => $q->where('status','active'))->count() }}</h2>
                <i class="mdi mdi-bank text-success float-right" style="font-size:40px;opacity:.3"></i>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card"><div class="card-body">
                <h5 class="card-title text-muted text-uppercase small">Expected Returns</h5>
                <h2 class="font-bold">{{ formatCurrencyShort(auth()->user()->fundingTransactions()->sum('expected_return')) }}</h2>
                <i class="mdi mdi-cash-multiple text-warning float-right" style="font-size:40px;opacity:.3"></i>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card"><div class="card-body">
                <h5 class="card-title text-muted text-uppercase small">Total Investments</h5>
                <h2 class="font-bold">{{ auth()->user()->fundingTransactions()->count() }}</h2>
                <i class="mdi mdi-chart-bar text-info float-right" style="font-size:40px;opacity:.3"></i>
            </div></div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-4">
            <a href="{{ route('client.marketplace.index') }}" class="card text-center p-4 h-100 text-decoration-none">
                <div class="card-body"><i class="mdi mdi-store text-primary" style="font-size:36px"></i><h5 class="mt-2">Browse Marketplace</h5><p class="text-muted small">Find loans to fund</p></div>
            </a>
        </div>
        <div class="col-md-4 mb-4">
            <a href="{{ route('client.investments.index') }}" class="card text-center p-4 h-100 text-decoration-none">
                <div class="card-body"><i class="mdi mdi-clipboard-list text-success" style="font-size:36px"></i><h5 class="mt-2">My Investments</h5><p class="text-muted small">View funded loans</p></div>
            </a>
        </div>
        <div class="col-md-4 mb-4">
            <a href="{{ route('client.earnings.index') }}" class="card text-center p-4 h-100 text-decoration-none">
                <div class="card-body"><i class="mdi mdi-cash-refund text-warning" style="font-size:36px"></i><h5 class="mt-2">My Earnings</h5><p class="text-muted small">View your returns</p></div>
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Recent Investments</h5>
                    @php $recentInv = auth()->user()->fundingTransactions()->with('loan')->latest()->take(5)->get(); @endphp
                    @if($recentInv->count())
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Loan</th><th>Amount</th><th>Status</th></tr></thead>
                            <tbody>
                            @foreach($recentInv as $inv)
                            <tr>
                                <td>{{ $inv->loan->reference ?? '#'.$inv->loan_id }}</td>
                                <td>N$ {{ number_format($inv->amount) }}</td>
                                <td><span class="badge badge-{{ $inv->status==='confirmed' ? 'success' : ($inv->status==='pending' ? 'warning' : 'secondary') }}">{{ ucfirst($inv->status) }}</span></td>
                            </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else<p class="text-muted">No investments yet. <a href="{{ route('client.marketplace.index') }}">Browse marketplace</a></p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Investment Performance</h5>
                    <div class="row text-center py-3">
                        <div class="col-6 border-right">
                            <h3 class="font-bold text-success">{{ auth()->user()->fundingTransactions()->count() }}</h3>
                            <small class="text-muted">Total Investments</small>
                        </div>
                        <div class="col-6">
                            <h3 class="font-bold text-primary">{{ formatCurrencyShort(auth()->user()->fundingTransactions()->where('status','confirmed')->sum('actual_return') ?? 0) }}</h3>
                            <small class="text-muted">Actual Returns</small>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <a href="{{ route('client.investments.index') }}" class="btn btn-sm btn-outline-primary">View All Investments</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
