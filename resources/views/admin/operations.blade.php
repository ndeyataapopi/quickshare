@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">Operations Dashboard</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Operations</li>
                </ol>
            </nav>
        </div>
    </div>
</div>

<div class="page-content container-fluid">

    @if (session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session()->get('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session()->get('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
    @endif

    {{-- System Alerts --}}
    @if(!empty($system_alerts))
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="card-title text-uppercase mb-0"><i class="mdi mdi-alert text-danger mr-2"></i>System Alerts</h5>
                </div>
                <div class="card-body py-3">
                    @foreach($system_alerts as $alert)
                        <a href="{{ $alert['route'] }}" class="alert alert-{{ $alert['type'] }} d-flex align-items-center mb-2 text-decoration-none">
                            <i class="mdi {{ $alert['icon'] }} mr-3" style="font-size:24px;"></i>
                            <span class="font-medium">{{ $alert['message'] }}</span>
                            <i class="mdi mdi-chevron-right ml-auto"></i>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Today's Loans --}}
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="card-title text-uppercase mb-0"><i class="mdi mdi-calendar-today text-primary mr-2"></i>Today's Loans</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center py-3">
                                <h2 class="font-weight-bold text-primary mb-0">{{ $todays_loans['submitted_today'] }}</h2>
                                <small class="text-muted text-uppercase">Submitted Today</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center py-3">
                                <h2 class="font-weight-bold text-warning mb-0">{{ $todays_loans['pending_review'] }}</h2>
                                <small class="text-muted text-uppercase">Pending Review</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center py-3">
                                <h2 class="font-weight-bold text-success mb-0">{{ $todays_loans['approved_today'] }}</h2>
                                <small class="text-muted text-uppercase">Approved Today</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center py-3">
                                <h2 class="font-weight-bold text-danger mb-0">{{ $todays_loans['rejected_today'] }}</h2>
                                <small class="text-muted text-uppercase">Rejected Today</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Operational Queues Row 1 --}}
    <div class="row">

        {{-- Pending KYC --}}
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h5 class="card-title text-uppercase mb-0"><i class="mdi mdi-account-card-details text-warning mr-2"></i>Pending KYC</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Pending Verification</span>
                        <span class="font-weight-bold">{{ $pending_kyc['pending_verification'] }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Resubmissions</span>
                        <span class="font-weight-bold">{{ $pending_kyc['resubmissions'] }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Oldest Pending</span>
                        <span class="font-weight-bold">{{ $pending_kyc['oldest_pending'] ?? '—' }}</span>
                    </div>
                    <a href="{{ $pending_kyc['view_route'] }}" class="btn btn-warning btn-sm btn-block">
                        <i class="mdi mdi-eye mr-1"></i>View Queue
                    </a>
                </div>
            </div>
        </div>

        {{-- Loans Awaiting Approval --}}
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h5 class="card-title text-uppercase mb-0"><i class="mdi mdi-cash text-info mr-2"></i>Loans Awaiting Approval</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Pending Requests</span>
                        <span class="font-weight-bold">{{ $loans_awaiting_approval['pending_count'] }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Oldest Waiting</span>
                        <span class="font-weight-bold">{{ $loans_awaiting_approval['oldest_waiting'] ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">High Value Loans</span>
                        <span class="font-weight-bold text-danger">{{ $loans_awaiting_approval['high_value_count'] }}</span>
                    </div>
                    <a href="{{ $loans_awaiting_approval['view_route'] }}" class="btn btn-info btn-sm btn-block">
                        <i class="mdi mdi-eye mr-1"></i>View Queue
                    </a>
                </div>
            </div>
        </div>

        {{-- Funding Awaiting Approval --}}
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h5 class="card-title text-uppercase mb-0"><i class="mdi mdi-bank-transfer-in text-success mr-2"></i>Funding Awaiting Approval</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Pending Proofs</span>
                        <span class="font-weight-bold">{{ $funding_awaiting_approval['pending_proofs'] }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Oldest Waiting</span>
                        <span class="font-weight-bold">{{ $funding_awaiting_approval['oldest_waiting'] ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Total Amount</span>
                        <span class="font-weight-bold">N$ {{ number_format($funding_awaiting_approval['total_amount'], 2) }}</span>
                    </div>
                    <a href="{{ $funding_awaiting_approval['view_route'] }}" class="btn btn-success btn-sm btn-block">
                        <i class="mdi mdi-eye mr-1"></i>View Queue
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Operational Queues Row 2 --}}
    <div class="row">

        {{-- Borrower Disbursements Awaiting Processing --}}
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h5 class="card-title text-uppercase mb-0"><i class="mdi mdi-send text-primary mr-2"></i>Disbursements Awaiting Processing</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Count</span>
                        <span class="font-weight-bold">{{ $disbursements_awaiting['count'] }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Total Amount</span>
                        <span class="font-weight-bold">N$ {{ number_format($disbursements_awaiting['total_amount'], 2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Oldest Waiting</span>
                        <span class="font-weight-bold">{{ $disbursements_awaiting['oldest_waiting'] ?? '—' }}</span>
                    </div>
                    <a href="{{ $disbursements_awaiting['view_route'] }}" class="btn btn-primary btn-sm btn-block">
                        <i class="mdi mdi-eye mr-1"></i>View Queue
                    </a>
                </div>
            </div>
        </div>

        {{-- Borrower Confirmations Awaiting --}}
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h5 class="card-title text-uppercase mb-0"><i class="mdi mdi-account-check text-info mr-2"></i>Borrower Confirmations Awaiting</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Count</span>
                        <span class="font-weight-bold">{{ $borrower_confirmations['count'] }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Oldest Waiting</span>
                        <span class="font-weight-bold">{{ $borrower_confirmations['oldest_waiting'] ?? '—' }}</span>
                    </div>
                    <a href="{{ $borrower_confirmations['view_route'] }}" class="btn btn-info btn-sm btn-block">
                        <i class="mdi mdi-eye mr-1"></i>View Queue
                    </a>
                </div>
            </div>
        </div>

        {{-- Repayments Awaiting Approval --}}
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h5 class="card-title text-uppercase mb-0"><i class="mdi mdi-cash-usd text-warning mr-2"></i>Repayments Awaiting Approval</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Count</span>
                        <span class="font-weight-bold">{{ $repayments_awaiting['count'] }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Total Amount</span>
                        <span class="font-weight-bold">N$ {{ number_format($repayments_awaiting['total_amount'], 2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Oldest Waiting</span>
                        <span class="font-weight-bold">{{ $repayments_awaiting['oldest_waiting'] ?? '—' }}</span>
                    </div>
                    <a href="{{ $repayments_awaiting['view_route'] }}" class="btn btn-warning btn-sm btn-block">
                        <i class="mdi mdi-eye mr-1"></i>View Queue
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Operational Queues Row 3 --}}
    <div class="row">

        {{-- Lender Payouts Awaiting --}}
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h5 class="card-title text-uppercase mb-0"><i class="mdi mdi-bank text-success mr-2"></i>Lender Payouts Awaiting</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Lenders Waiting</span>
                        <span class="font-weight-bold">{{ $lender_payouts['lenders_waiting'] }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Total Payout Amount</span>
                        <span class="font-weight-bold">N$ {{ number_format($lender_payouts['total_amount'], 2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Oldest Payout</span>
                        <span class="font-weight-bold">{{ $lender_payouts['oldest_payout'] ?? '—' }}</span>
                    </div>
                    <a href="{{ $lender_payouts['view_route'] }}" class="btn btn-success btn-sm btn-block">
                        <i class="mdi mdi-eye mr-1"></i>View Queue
                    </a>
                </div>
            </div>
        </div>

        {{-- Failed Jobs --}}
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h5 class="card-title text-uppercase mb-0"><i class="mdi mdi-alert-octagon text-danger mr-2"></i>Failed Jobs</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Failed Job Count</span>
                        <span class="font-weight-bold {{ $failed_jobs['count'] > 0 ? 'text-danger' : 'text-success' }}">{{ $failed_jobs['count'] }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-muted">Latest Failure</span>
                        <span class="font-weight-bold">{{ $failed_jobs['latest_failure'] ?? '—' }}</span>
                    </div>
                    @if($failed_jobs['count'] > 0)
                    <form method="POST" action="{{ $failed_jobs['retry_route'] }}" class="mb-2">
                        @csrf
                        <button type="submit" class="btn btn-danger btn-sm btn-block">
                            <i class="mdi mdi-refresh mr-1"></i>Retry All Failed
                        </button>
                    </form>
                    @endif
                    <a href="{{ $failed_jobs['view_route'] }}" class="btn btn-outline-secondary btn-sm btn-block">
                        <i class="mdi mdi-eye mr-1"></i>View Failed Jobs
                    </a>
                </div>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-white">
                    <h5 class="card-title text-uppercase mb-0"><i class="mdi mdi-speedometer text-primary mr-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body d-flex flex-column">
                    <a href="{{ route('admin.kyc.index') }}" class="btn btn-outline-warning btn-sm mb-2 text-left">
                        <i class="mdi mdi-account-card-details mr-1"></i>KYC Review Queue
                    </a>
                    <a href="{{ route('admin.funding-payments.index') }}" class="btn btn-outline-success btn-sm mb-2 text-left">
                        <i class="mdi mdi-bank-transfer-in mr-1"></i>Funding Payments
                    </a>
                    <a href="{{ route('admin.disbursements.index') }}" class="btn btn-outline-primary btn-sm mb-2 text-left">
                        <i class="mdi mdi-send mr-1"></i>Disbursements
                    </a>
                    <a href="{{ route('admin.repayments.index') }}" class="btn btn-outline-info btn-sm mb-2 text-left">
                        <i class="mdi mdi-cash-usd mr-1"></i>Repayments
                    </a>
                    <a href="{{ route('admin.system-status.index') }}" class="btn btn-outline-secondary btn-sm text-left">
                        <i class="mdi mdi-heart-pulse mr-1"></i>System Status
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
