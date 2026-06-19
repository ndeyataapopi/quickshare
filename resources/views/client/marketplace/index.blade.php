@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">Marketplace</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">Marketplace</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>
    @endif
    <div class="row">
        @forelse($loans as $loan)
        <div class="col-md-6 col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="card-title mb-0">{{ $loan->reference }}</h6>
                        <span class="badge badge-info">{{ ucfirst($loan->status) }}</span>
                    </div>
                    @php
                        $target = (float) ($loan->approved_amount ?? $loan->requested_amount);
                        $funded = (float) ($loan->funded_amount ?? 0);
                        $remaining = max(0, $target - $funded);
                        $pct = $target > 0 ? min(100, round(($funded / $target) * 100)) : 0;
                        $minFund = config('loans.min_funding_amount', 500);
                    @endphp
                    <h4 class="text-primary mb-1">{{ kpiMoney($target) }}</h4>
                    <p class="text-muted small mb-2">{{ $loan->purpose }}</p>
                    <div class="d-flex justify-content-between small text-muted mb-3">
                        <span><i class="mdi mdi-calendar"></i> {{ $loan->loan_term_days }} days</span>
                        <span><i class="mdi mdi-percent"></i> {{ $loan->interest_rate ?? '-' }}% p.a.</span>
                    </div>
                    <div class="mb-1 d-flex justify-content-between small">
                        <span>Funded</span><span>{{ $pct }}%</span>
                    </div>
                    <div class="progress mb-3" style="height:6px;">
                        <div class="progress-bar bg-success" style="width:{{ $pct }}%"></div>
                    </div>
                    <form action="{{ route('client.marketplace.fund', $loan) }}" method="POST">
                        @csrf
                        <div class="input-group">
                            <input type="number" name="amount" class="form-control form-control-sm"
                                placeholder="Min N$ {{ $minFund }}"
                                min="{{ $minFund }}" max="{{ $remaining }}" step="0.01" required>
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-primary btn-sm">Fund</button>
                            </div>
                        </div>
                        <small class="text-muted">Remaining: N$ {{ number_format($remaining, 2) }}</small>
                    </form>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="mdi mdi-tune text-muted" style="font-size:64px;"></i>
                    <h5 class="mt-3 text-muted">No Listings Available</h5>
                    <p class="text-muted">No loans are available for funding at the moment.</p>
                </div>
            </div>
        </div>
        @endforelse
    </div>
    @if(method_exists($loans, 'links'))
    <div class="mt-3">{{ $loans->links() }}</div>
    @endif
</div>
@endsection
