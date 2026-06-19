@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0"><i class="mdi mdi-chart-line mr-2"></i>Reports</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">Reports</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    <form method="GET" class="mb-4">
        <div class="row align-items-end">
            <div class="col-md-3">
                <label class="form-label">Period</label>
                <select name="period" class="form-control" onchange="this.form.submit()">
                    <option value="today" {{ $period === 'today' ? 'selected' : '' }}>Today</option>
                    <option value="week" {{ $period === 'week' ? 'selected' : '' }}>This Week</option>
                    <option value="month" {{ $period === 'month' ? 'selected' : '' }}>This Month</option>
                    <option value="quarter" {{ $period === 'quarter' ? 'selected' : '' }}>This Quarter</option>
                    <option value="year" {{ $period === 'year' ? 'selected' : '' }}>This Year</option>
                </select>
            </div>
            <div class="col-md-9">
                <h5 class="text-muted mb-0">Showing statistics for <strong>{{ ucfirst($period) }}</strong></h5>
            </div>
        </div>
    </form>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-primary mb-0">{{ formatKpi($stats['total_loans']) }}</h4>
                    <small class="text-muted">Total Loans</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-info mb-0">{{ kpiMoney($stats['total_loan_amount']) }}</h4>
                    <small class="text-muted">Total Loan Amount</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-success mb-0">{{ formatKpi($stats['active_loans']) }}</h4>
                    <small class="text-muted">Active Loans</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-warning mb-0">{{ formatKpi($stats['completed_loans']) }}</h4>
                    <small class="text-muted">Completed Loans</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-success mb-0">{{ kpiMoney($stats['total_repayments']) }}</h4>
                    <small class="text-muted">Total Repayments</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-info mb-0">{{ kpiMoney($stats['total_funding']) }}</h4>
                    <small class="text-muted">Total Funding</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-primary mb-0">{{ formatKpi($stats['new_users']) }}</h4>
                    <small class="text-muted">New Users</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-success mb-0">{{ formatKpi($stats['verified_users']) }}</h4>
                    <small class="text-muted">Verified Users</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3">
            <a href="{{ route('admin.reports.show', ['type' => 'loans', 'period' => $period]) }}" class="card card-body text-center text-decoration-none h-100">
                <i class="mdi mdi-cash text-primary mb-2" style="font-size:48px;"></i>
                <h5 class="mb-1">Loans Report</h5>
                <p class="text-muted small mb-0">Detailed loan performance</p>
            </a>
        </div>
        <div class="col-md-3">
            <a href="{{ route('admin.reports.show', ['type' => 'users', 'period' => $period]) }}" class="card card-body text-center text-decoration-none h-100">
                <i class="mdi mdi-account-multiple text-success mb-2" style="font-size:48px;"></i>
                <h5 class="mb-1">Users Report</h5>
                <p class="text-muted small mb-0">User registrations & activity</p>
            </a>
        </div>
        <div class="col-md-3">
            <a href="{{ route('admin.reports.show', ['type' => 'repayments', 'period' => $period]) }}" class="card card-body text-center text-decoration-none h-100">
                <i class="mdi mdi-cash-usd text-warning mb-2" style="font-size:48px;"></i>
                <h5 class="mb-1">Repayments Report</h5>
                <p class="text-muted small mb-0">Repayment trends & defaults</p>
            </a>
        </div>
        <div class="col-md-3">
            <a href="{{ route('admin.reports.show', ['type' => 'funding', 'period' => $period]) }}" class="card card-body text-center text-decoration-none h-100">
                <i class="mdi mdi-bank-transfer-in text-info mb-2" style="font-size:48px;"></i>
                <h5 class="mb-1">Funding Report</h5>
                <p class="text-muted small mb-0">Investment & funding data</p>
            </a>
        </div>
    </div>
</div>
@endsection
