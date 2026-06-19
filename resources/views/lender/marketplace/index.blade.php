@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <div class="row page-titles">
        <div class="col-md-5 align-self-center"><h4 class="text-themecolor">Loan Marketplace</h4></div>
        <div class="col-md-7 align-self-center text-right">
            <ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li><li class="breadcrumb-item active">Marketplace</li></ol>
        </div>
    </div>
    @if(session('success'))<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>@endif
    @if(session('error'))<div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>@endif

    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form class="row" method="GET">
                        <div class="col-md-3">
                            <input type="text" name="search" class="form-control" placeholder="Search loans..." value="{{ request('search') }}">
                        </div>
                        <div class="col-md-2">
                            <select name="amount_range" class="form-control">
                                <option value="">Any Amount</option>
                                <option value="0-5000" {{ request('amount_range')==='0-5000' ? 'selected' : '' }}>N$ 0 – 5K</option>
                                <option value="5000-15000" {{ request('amount_range')==='5000-15000' ? 'selected' : '' }}>N$ 5K – 15K</option>
                                <option value="15000+" {{ request('amount_range')==='15000+' ? 'selected' : '' }}>N$ 15K+</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="rate" class="form-control">
                                <option value="">Any Rate</option>
                                <option value="low" {{ request('rate')==='low' ? 'selected' : '' }}>Low (8–12%)</option>
                                <option value="medium" {{ request('rate')==='medium' ? 'selected' : '' }}>Medium (12–18%)</option>
                                <option value="high" {{ request('rate')==='high' ? 'selected' : '' }}>High (18%+)</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary btn-block"><i class="mdi mdi-filter mr-1"></i>Filter</button>
                        </div>
                        <div class="col-md-2">
                            <a href="{{ route('client.marketplace.index') }}" class="btn btn-outline-secondary btn-block">Reset</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        @forelse($loans ?? [] as $loan)
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="font-weight-bold">{{ $loan->reference }}</span>
                        <span class="badge badge-info">{{ ucfirst(str_replace('_',' ',$loan->status)) }}</span>
                    </div>
                    <h4 class="text-primary mb-1">N$ {{ number_format($loan->approved_amount ?? $loan->requested_amount) }}</h4>
                    <div class="d-flex justify-content-between small text-muted mb-3">
                        <span><i class="mdi mdi-calendar mr-1"></i>{{ $loan->loan_term_days ?? '-' }} days</span>
                        <span><i class="mdi mdi-percent mr-1"></i>{{ $loan->interest_rate ?? '-' }}% p.a.</span>
                    </div>
                    @php
                        $funded = $loan->funded_amount ?? 0;
                        $total = $loan->approved_amount ?? $loan->requested_amount ?? 1;
                        $pct = $total > 0 ? min(100, round($funded/$total*100)) : 0;
                    @endphp
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Funding Progress</span><span>{{ $pct }}%</span>
                    </div>
                    <div class="progress mb-3" style="height:6px">
                        <div class="progress-bar bg-success" style="width:{{ $pct }}%"></div>
                    </div>
                    <div class="d-flex justify-content-between small text-muted mb-3">
                        <span>Trust Score: <strong>{{ $loan->borrower->trust_score ?? 0 }}/100</strong></span>
                        <span>Remaining: <strong>N$ {{ number_format($total - $funded) }}</strong></span>
                    </div>
                    <button type="button" class="btn btn-primary btn-block btn-sm" data-toggle="modal" data-target="#fundModal{{ $loan->id }}">
                        <i class="mdi mdi-cash-plus mr-1"></i>Fund This Loan
                    </button>
                </div>
            </div>
        </div>

        <!-- Fund Modal -->
        <div class="modal fade" id="fundModal{{ $loan->id }}" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Fund Loan: {{ $loan->reference }}</h5>
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    </div>
                    <form method="POST" action="{{ route('client.marketplace.fund', $loan) }}">
                        @csrf
                        <div class="modal-body">
                            <div class="row text-center mb-3">
                                <div class="col-6"><small class="text-muted">Amount</small><div class="font-weight-bold">N$ {{ number_format($loan->approved_amount ?? $loan->requested_amount) }}</div></div>
                                <div class="col-6"><small class="text-muted">Interest Rate</small><div class="font-weight-bold">{{ $loan->interest_rate }}%</div></div>
                            </div>
                            <div class="row text-center mb-3">
                                <div class="col-6"><small class="text-muted">Borrower Trust</small><div class="font-weight-bold">{{ $loan->borrower->trust_score ?? 0 }}/100</div></div>
                                <div class="col-6"><small class="text-muted">Remaining</small><div class="font-weight-bold text-success">N$ {{ number_format($total - $funded) }}</div></div>
                            </div>
                            <div class="form-group">
                                <label>Investment Amount (N$)</label>
                                <input type="number" name="amount" class="form-control" min="100" max="{{ $total - $funded }}" placeholder="e.g. 1000" required>
                                <small class="text-muted">Min: N$100 | Available: N$ {{ number_format($total - $funded) }}</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Confirm Investment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="mdi mdi-store text-muted" style="font-size:64px;"></i>
                    <h5 class="mt-3 text-muted">No Loans Available</h5>
                    <p class="text-muted">No loans are currently available for funding. Check back soon.</p>
                </div>
            </div>
        </div>
        @endforelse
    </div>

    @if(isset($loans) && $loans instanceof \Illuminate\Pagination\LengthAwarePaginator)
    <div class="mt-3">{{ $loans->withQueryString()->links() }}</div>
    @endif
</div>
@endsection
