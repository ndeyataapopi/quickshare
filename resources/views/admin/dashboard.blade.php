@extends('layouts.app')
@section('content')
<!-- ============================================================== -->
<!-- Bread crumb and right sidebar toggle -->
<!-- ============================================================== -->
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">Admin Dashboard</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
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
                    <h5 class="card-title text-uppercase">Total Users</h5>
                    <div class="d-flex align-items-center mb-2 mt-4">
                        <h2 class="mb-0 display-5"><i class="mdi mdi-account-multiple text-primary"></i></h2>
                        <div class="ml-auto">
                            <h2 class="mb-0 display-6"><span class="font-normal">{{ $stats['total_users'] }}</span></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase">Active Loans</h5>
                    <div class="d-flex align-items-center mb-2 mt-4">
                        <h2 class="mb-0 display-5"><i class="mdi mdi-cash text-success"></i></h2>
                        <div class="ml-auto">
                            <h2 class="mb-0 display-6"><span class="font-normal">{{ $stats['active_loans'] }}</span></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase">Total Funded</h5>
                    <div class="d-flex align-items-center mb-2 mt-4">
                        <h2 class="mb-0 display-5"><i class="mdi mdi-trending-up text-info"></i></h2>
                        <div class="ml-auto">
                            <h2 class="mb-0 display-6"><span class="font-normal">{{ formatCurrencyShort($stats['total_funded']) }}</span></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase">Pending KYC</h5>
                    <div class="d-flex align-items-center mb-2 mt-4">
                        <h2 class="mb-0 display-5"><i class="mdi mdi-account-card-details text-warning"></i></h2>
                        <div class="ml-auto">
                            <h2 class="mb-0 display-6"><span class="font-normal">{{ $stats['pending_kyc'] }}</span></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase">Platform Earnings</h5>
                    <div class="d-flex align-items-center mb-2 mt-4">
                        <h2 class="mb-0 display-5"><i class="mdi mdi-cash-multiple text-success"></i></h2>
                        <div class="ml-auto">
                            <h2 class="mb-0 display-6"><span class="font-normal">{{ formatCurrencyShort($earningsSummary['total_earnings'] ?? 0) }}</span></h2>
                        </div>
                    </div>
                    <small class="text-muted">ROI: {{ $earningsSummary['roi'] ?? 0 }}%</small>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase">Total Revenue</h5>
                    <div class="d-flex align-items-center mb-2 mt-4">
                        <h2 class="mb-0 display-5"><i class="mdi mdi-chart-line text-info"></i></h2>
                        <div class="ml-auto">
                            <h2 class="mb-0 display-6"><span class="font-normal">{{ formatCurrencyShort($revenueStats['total_revenue'] ?? 0) }}</span></h2>
                        </div>
                    </div>
                    <small class="text-muted">Today: {{ formatCurrencyShort($revenueStats['revenue_today'] ?? 0) }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase">Platform Fees</h5>
                    <div class="d-flex align-items-center mb-2 mt-4">
                        <h2 class="mb-0 display-5"><i class="mdi mdi-percent text-primary"></i></h2>
                        <div class="ml-auto">
                            <h2 class="mb-0 display-6"><span class="font-normal">{{ formatCurrencyShort($revenueStats['total_platform_fees'] ?? 0) }}</span></h2>
                        </div>
                    </div>
                    <small class="text-muted">This month: {{ formatCurrencyShort($revenueStats['revenue_this_month'] ?? 0) }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase">Total Invested</h5>
                    <div class="d-flex align-items-center mb-2 mt-4">
                        <h2 class="mb-0 display-5"><i class="mdi mdi-bank text-warning"></i></h2>
                        <div class="ml-auto">
                            <h2 class="mb-0 display-6"><span class="font-normal">{{ formatCurrencyShort($earningsSummary['total_invested'] ?? 0) }}</span></h2>
                        </div>
                    </div>
                    <small class="text-muted">Expected: {{ formatCurrencyShort($earningsSummary['total_expected_return'] ?? 0) }}</small>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- Quick Actions Row  -->
    <!-- ============================================================== -->
    <div class="row">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Users</h5>
                    <p class="text-muted mb-3">Manage platform users</p>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-primary btn-sm">Manage Users</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">KYC Reviews</h5>
                    <p class="text-muted mb-3">{{ $stats['pending_kyc'] }} pending reviews</p>
                    <a href="{{ route('admin.kyc.index') }}" class="btn btn-warning btn-sm">Review KYC</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Loans</h5>
                    <p class="text-muted mb-3">Manage loan applications</p>
                    <a href="{{ route('admin.loans.index') }}" class="btn btn-success btn-sm">Manage Loans</a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Fraud Alerts</h5>
                    <p class="text-muted mb-3">Review suspicious activity</p>
                    <a href="{{ route('admin.fraud.index') }}" class="btn btn-danger btn-sm">View Alerts</a>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================== -->
    <!-- Recent Loans & Activity Row  -->
    <!-- ============================================================== -->
    <div class="row">
        <div class="col-md-12 col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Recent Loans</h5>
                    @if($recentLoans->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Reference</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentLoans as $loan)
                                    <tr>
                                        <td>{{ $loan->reference }}</td>
                                        <td>N$ {{ number_format((float) ($loan->approved_amount ?? $loan->requested_amount)) }}</td>
                                        <td>
                                            @php
                                                $badgeClass = match($loan->status) {
                                                    'marketplace', 'partially_funded', 'funded' => 'success',
                                                    'pending_review' => 'warning',
                                                    'active', 'disbursed' => 'primary',
                                                    'cancelled' => 'danger',
                                                    'defaulted', 'overdue' => 'danger',
                                                    'completed' => 'info',
                                                    default => 'secondary',
                                                };
                                            @endphp
                                            <span class="badge badge-{{ $badgeClass }}">
                                                {{ ucfirst(str_replace('_', ' ', $loan->status)) }}
                                            </span>
                                        </td>
                                        <td>{{ $loan->created_at->format('M j, Y') }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <a href="{{ route('admin.loans.index') }}" class="btn btn-sm btn-primary">View All Loans</a>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="mdi mdi-cash text-muted" style="font-size: 48px;"></i>
                            <p class="text-muted mt-2">No loans yet</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-12 col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Recent Activity</h5>
                    @if($recentActivity->count() > 0)
                        <div class="list-group">
                            @foreach($recentActivity as $activity)
                            <div class="list-group-item">
                                <div class="d-flex align-items-center">
                                    <div class="mr-3">
                                        <i class="mdi mdi-information text-primary"></i>
                                    </div>
                                    <div>
                                        <p class="mb-0">{{ $activity->description ?? 'System activity' }}</p>
                                        <small class="text-muted">{{ $activity->created_at->diffForHumans() }}</small>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="mdi mdi-history text-muted" style="font-size: 48px;"></i>
                            <p class="text-muted mt-2">No recent activity</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
<!-- ============================================================== -->
<!-- End Container fluid  -->
<!-- ============================================================== -->
@endsection
