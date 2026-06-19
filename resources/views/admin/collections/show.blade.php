@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">Collection Details</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.collections.index') }}">Collections</a></li>
                    <li class="breadcrumb-item active">Details</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-4">Collection Case</h5>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Borrower</div><div class="col-sm-8 font-weight-bold">{{ optional($collection->loan->borrower)->first_name }} {{ optional($collection->loan->borrower)->last_name }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Loan Reference</div><div class="col-sm-8">{{ optional($collection->loan)->reference }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Loan Amount</div><div class="col-sm-8">N\$ {{ number_format(optional($collection->loan)->amount ?? 0) }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Overdue Amount</div><div class="col-sm-8 text-danger font-weight-bold">N\$ {{ number_format($collection->overdue_amount ?? 0) }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Days Overdue</div><div class="col-sm-8">{{ $collection->days_overdue ?? '-' }} days</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Status</div><div class="col-sm-8"><span class="badge badge-{{ $collection->status === 'resolved' ? 'success' : 'warning' }}">{{ ucfirst($collection->status ?? 'open') }}</span></div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Notes</div><div class="col-sm-8">{{ $collection->notes ?? '-' }}</div></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Actions</h5>
                    <a href="{{ route('admin.collections.index') }}" class="btn btn-outline-secondary btn-block"><i class="mdi mdi-arrow-left"></i> Back to Collections</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
