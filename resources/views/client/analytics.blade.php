@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">Analytics</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">Analytics</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    @php
        $user = auth()->user();
        $totalLoans      = $user->loans()->count();
        $activeLoans     = $user->loans()->whereIn('status', ['active', 'disbursed'])->count();
        $completedLoans  = $user->loans()->where('status', 'completed')->count();
        $defaultedLoans  = $user->loans()->where('status', 'defaulted')->count();
        $totalBorrowed   = $user->loans()->whereNotNull('approved_amount')->sum('approved_amount');
        $totalRepaid     = $user->repayments()->where('status', 'completed')->sum('amount');
        $totalInvested   = $user->fundingTransactions()->where('status', 'confirmed')->sum('amount');
        $totalExpected   = $user->fundingTransactions()->where('status', 'confirmed')->sum('expected_return');
        $score           = (float) $user->trust_score;
        $tier            = \App\Modules\TrustScore\Services\TrustScoreService::getTier($score);
    @endphp

    <div class="row">
        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-row">
                        <div class="round round-lg align-self-center round-info"><i class="mdi mdi-cash-multiple"></i></div>
                        <div class="ml-2 align-self-center">
                            <h3 class="mb-0 font-weight-bold">{{ $totalLoans }}</h3>
                            <h5 class="text-muted mb-0">Total Loans</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-row">
                        <div class="round round-lg align-self-center round-success"><i class="mdi mdi-check-circle"></i></div>
                        <div class="ml-2 align-self-center">
                            <h3 class="mb-0 font-weight-bold">{{ $completedLoans }}</h3>
                            <h5 class="text-muted mb-0">Completed Loans</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-row">
                        <div class="round round-lg align-self-center round-warning"><i class="mdi mdi-trending-up"></i></div>
                        <div class="ml-2 align-self-center">
                            <h3 class="mb-0 font-weight-bold">N$ {{ number_format($totalInvested, 2) }}</h3>
                            <h5 class="text-muted mb-0">Total Invested</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-row">
                        <div class="round round-lg align-self-center round-danger"><i class="mdi mdi-account-star"></i></div>
                        <div class="ml-2 align-self-center">
                            <h3 class="mb-0 font-weight-bold">{{ number_format($score, 1) }}</h3>
                            <h5 class="text-muted mb-0">Trust Score ({{ ucfirst($tier) }})</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Borrower Summary</h5>
                    <table class="table table-bordered">
                        <tbody>
                            <tr><td class="text-muted">Total Borrowed</td><td class="font-weight-bold">N$ {{ number_format($totalBorrowed, 2) }}</td></tr>
                            <tr><td class="text-muted">Total Repaid</td><td class="font-weight-bold">N$ {{ number_format($totalRepaid, 2) }}</td></tr>
                            <tr><td class="text-muted">Active Loans</td><td>{{ $activeLoans }}</td></tr>
                            <tr><td class="text-muted">Completed Loans</td><td>{{ $completedLoans }}</td></tr>
                            <tr><td class="text-muted">Defaulted Loans</td><td class="{{ $defaultedLoans > 0 ? 'text-danger' : '' }}">{{ $defaultedLoans }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Lender Summary</h5>
                    <table class="table table-bordered">
                        <tbody>
                            <tr><td class="text-muted">Total Invested</td><td class="font-weight-bold">N$ {{ number_format($totalInvested, 2) }}</td></tr>
                            <tr><td class="text-muted">Expected Returns</td><td class="font-weight-bold text-success">N$ {{ number_format($totalExpected, 2) }}</td></tr>
                            <tr><td class="text-muted">Expected Profit</td><td class="text-success">N$ {{ number_format($totalExpected - $totalInvested, 2) }}</td></tr>
                            <tr><td class="text-muted">Trust Score</td><td>{{ number_format($score, 2) }} / 100</td></tr>
                            <tr><td class="text-muted">Trust Tier</td><td><span class="badge badge-info">{{ ucfirst($tier) }}</span></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Recent Loans</h5>
                    @php $recentLoans = $user->loans()->latest()->take(10)->get(); @endphp
                    @if($recentLoans->isEmpty())
                        <p class="text-muted">No loans yet. <a href="{{ route('client.loans.create') }}">Apply for a loan</a>.</p>
                    @else
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>Reference</th><th>Amount</th><th>Term</th><th>Status</th><th>Date</th></tr></thead>
                            <tbody>
                                @foreach($recentLoans as $loan)
                                <tr>
                                    <td><a href="{{ route('client.loans.show', $loan) }}">{{ $loan->reference }}</a></td>
                                    <td>N$ {{ number_format($loan->requested_amount, 2) }}</td>
                                    <td>{{ $loan->loan_term_days }} days</td>
                                    <td><span class="badge badge-{{ ['pending_review'=>'warning','marketplace'=>'info','active'=>'primary','completed'=>'success','defaulted'=>'danger','cancelled'=>'secondary'][$loan->status] ?? 'secondary' }}">{{ ucwords(str_replace('_',' ',$loan->status)) }}</span></td>
                                    <td>{{ $loan->created_at->format('M j, Y') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
