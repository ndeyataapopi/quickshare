@extends('layouts.app')
@section('content')
<!-- ============================================================== -->
<!-- Bread crumb and right sidebar toggle -->
<!-- ============================================================== -->
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">Dashboard</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<!-- ============================================================== -->
<!-- End Bread crumb and right sidebar toggle -->
<!-- ============================================================== -->

<!-- ============================================================== -->
<!-- Container fluid  -->
<!-- ============================================================== -->
<div class="page-content container-fluid">

    <!-- Flash Messages -->
    @if (session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session()->get('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session()->get('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <!-- ============================================================== -->
    <!-- First Cards Row  -->
    <!-- ============================================================== -->
    <div class="row">
        <div class="col-md-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase">Trust Score</h5>
                    <div class="d-flex align-items-center mb-2 mt-4">
                        <h2 class="mb-0 display-5"><i class="mdi mdi-speedometer text-info"></i></h2>
                        <div class="ml-auto">
                            <h2 class="mb-0 display-6"><span class="font-normal">{{ number_format($user->trust_score ?? 0) }}</span></h2>
                        </div>
                    </div>
                    <span class="badge badge-{{ $user->trust_tier === 'platinum' ? 'primary' : ($user->trust_tier === 'gold' ? 'warning' : ($user->trust_tier === 'silver' ? 'secondary' : 'info')) }}">
                        {{ ucfirst($user->trust_tier ?? 'bronze') }} Tier
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase">Active Loans</h5>
                    <div class="d-flex align-items-center mb-2 mt-4">
                        <h2 class="mb-0 display-5"><i class="mdi mdi-cash text-primary"></i></h2>
                        <div class="ml-auto">
                            <h2 class="mb-0 display-6"><span class="font-normal">{{ $loans->where('status', 'active')->count() }}</span></h2>
                        </div>
                    </div>
                    <small class="text-muted">Total: N$ {{ number_format($loans->sum('requested_amount')) }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase">Investments</h5>
                    <div class="d-flex align-items-center mb-2 mt-4">
                        <h2 class="mb-0 display-5"><i class="mdi mdi-trending-up text-success"></i></h2>
                        <div class="ml-auto">
                            <h2 class="mb-0 display-6"><span class="font-normal">{{ $investments->count() }}</span></h2>
                        </div>
                    </div>
                    <small class="text-muted">Total: N$ {{ number_format($investments->sum('amount')) }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase">Earnings</h5>
                    <div class="d-flex align-items-center mb-2 mt-4">
                        <h2 class="mb-0 display-5"><i class="mdi mdi-wallet text-warning"></i></h2>
                        <div class="ml-auto">
                            <h2 class="mb-0 display-6"><span class="font-normal">{{ $earnings->count() }}</span></h2>
                        </div>
                    </div>
                    <small class="text-muted">Total: N$ {{ number_format($earnings->sum('actual_return')) }}</small>
                </div>
            </div>
        </div>
    </div>
    <!-- ============================================================== -->
    <!-- Recent Loans & Repayments Row  -->
    <!-- ============================================================== -->
    <div class="row">
        <div class="col-md-12 col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Recent Loans</h5>
                    @if($loans->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Amount</th>
                                        <th>Purpose</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($loans as $loan)
                                    <tr>
                                        <td>N$ {{ number_format($loan->requested_amount, 2) }}</td>
                                        <td>{{ $loan->purpose }}</td>
                                        <td>
                                            @php $b=['pending_review'=>'warning','marketplace'=>'info','partially_funded'=>'info','funded'=>'primary','disbursed'=>'primary','active'=>'primary','completed'=>'success','rejected'=>'danger','cancelled'=>'secondary','defaulted'=>'danger']; @endphp
                                            <span class="badge badge-{{ $b[$loan->status] ?? 'secondary' }}">
                                                {{ ucwords(str_replace('_',' ',$loan->status)) }}
                                            </span>
                                        </td>
                                        <td>{{ $loan->created_at->format('M j, Y') }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <a href="{{ route('client.loans.index') }}" class="btn btn-sm btn-primary">View All Loans</a>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="mdi mdi-cash text-muted" style="font-size: 48px;"></i>
                            <p class="text-muted mt-2">No loans yet</p>
                            <a href="{{ route('client.loans.create') }}" class="btn btn-sm btn-primary">Apply for a Loan</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-12 col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Recent Repayments</h5>
                    @if($repayments->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Amount</th>
                                        <th>Loan</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($repayments as $repayment)
                                    <tr>
                                        <td>N$ {{ number_format($repayment->amount, 2) }}</td>
                                        <td>{{ $repayment->loan_id }}</td>
                                        <td>
                                            @php $sc=['completed'=>'success','overdue'=>'danger','pending'=>'warning']; @endphp
                                            <span class="badge badge-{{ $sc[$repayment->status] ?? 'secondary' }}">
                                                {{ ucfirst($repayment->status) }}
                                            </span>
                                        </td>
                                        <td>{{ $repayment->created_at->format('M j, Y') }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <a href="{{ route('client.repayments.index') }}" class="btn btn-sm btn-primary">View All Repayments</a>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="mdi mdi-cash-usd text-muted" style="font-size: 48px;"></i>
                            <p class="text-muted mt-2">No repayments yet</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- Recent Investments & Trust Score Row  -->
    <!-- ============================================================== -->
    <div class="row">
        <div class="col-md-12 col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Recent Investments</h5>
                    @if($investments->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Amount</th>
                                        <th>Loan</th>
                                        <th>Interest</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($investments as $investment)
                                    <tr>
                                        <td>N$ {{ number_format($investment->amount) }}</td>
                                        <td>#{{ $investment->loan_id }}</td>
                                        <td>{{ $investment->interest_rate }}%</td>
                                        <td>{{ $investment->created_at->format('M j, Y') }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <a href="{{ route('client.investments.index') }}" class="btn btn-sm btn-primary">View All Investments</a>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="mdi mdi-trending-up text-muted" style="font-size: 48px;"></i>
                            <p class="text-muted mt-2">No investments yet</p>
                            <a href="{{ route('client.marketplace.index') }}" class="btn btn-sm btn-primary">Browse Marketplace</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-12 col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Trust Score Details</h5>
                    <div class="d-flex align-items-center justify-content-center py-4">
                        <div class="text-center">
                            <h1 class="display-4 font-weight-bold text-primary">{{ number_format($user->trust_score ?? 0) }}</h1>
                            <p class="text-muted">{{ ucfirst($user->trust_tier ?? 'bronze') }} Tier</p>
                            <a href="{{ route('client.trust-score.index') }}" class="btn btn-sm btn-outline-primary mt-2">View Details</a>
                        </div>
                    </div>
                    <div class="mt-3">
                        <small class="text-muted">Build your trust score by:</small>
                        <ul class="list-unstyled mt-2">
                            <li class="mb-1"><i class="mdi mdi-check text-success"></i> Making timely repayments</li>
                            <li class="mb-1"><i class="mdi mdi-check text-success"></i> Completing KYC verification</li>
                            <li class="mb-1"><i class="mdi mdi-check text-success"></i> Referring new users</li>
                            <li class="mb-1"><i class="mdi mdi-check text-success"></i> Active platform usage</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- ============================================================== -->
<!-- End Container fluid  -->
<!-- ============================================================== -->
@endsection
