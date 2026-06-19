@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">Loan Details</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('client.loans.index') }}">My Loans</a></li>
                    <li class="breadcrumb-item active">{{ $loan->reference }}</li>
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
                    <h5 class="card-title text-uppercase mb-4">Loan #{{ $loan->reference }}</h5>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Requested Amount</div><div class="col-sm-8 font-weight-bold">N$ {{ number_format($loan->requested_amount, 2) }}</div></div>
                    @if($loan->approved_amount)
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Approved Amount</div><div class="col-sm-8 font-weight-bold text-primary">N$ {{ number_format($loan->approved_amount, 2) }}</div></div>
                    @endif
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Interest Rate</div><div class="col-sm-8">{{ $loan->interest_rate ?? '-' }}%</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Platform Fee</div><div class="col-sm-8">N$ {{ number_format($loan->platform_fee ?? 0, 2) }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Total Repayment</div><div class="col-sm-8">N$ {{ number_format($loan->total_repayment ?? 0, 2) }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Term</div><div class="col-sm-8">{{ $loan->loan_term_days }} days</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Purpose</div><div class="col-sm-8">{{ $loan->purpose }}</div></div>
                    <div class="row mb-3">
                        <div class="col-sm-4 text-muted">Status</div>
                        <div class="col-sm-8">
                            @php $b=['pending_review'=>'warning','marketplace'=>'info','partially_funded'=>'info','funded'=>'primary','disbursed'=>'primary','active'=>'primary','completed'=>'success','rejected'=>'danger','cancelled'=>'secondary','defaulted'=>'danger']; @endphp
                            <span class="badge badge-{{ $b[$loan->status] ?? 'secondary' }}">{{ ucwords(str_replace('_',' ',$loan->status)) }}</span>
                        </div>
                    </div>
                    @if($loan->repayment_date)
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Due Date</div><div class="col-sm-8">{{ \Carbon\Carbon::parse($loan->repayment_date)->format('M j, Y') }}</div></div>
                    @endif
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Applied On</div><div class="col-sm-8">{{ $loan->created_at->format('M j, Y') }}</div></div>
                    @if($loan->admin_notes)
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Admin Notes</div><div class="col-sm-8 small">{{ $loan->admin_notes }}</div></div>
                    @endif
                    @if($loan->description)
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Description</div><div class="col-sm-8">{{ $loan->description }}</div></div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Quick Actions</h5>
                    <a href="{{ route('client.loans.index') }}" class="btn btn-outline-secondary btn-block mb-2">
                        <i class="mdi mdi-arrow-left mr-1"></i> Back to Loans
                    </a>
                    <a href="{{ route('client.repayments.index') }}" class="btn btn-outline-primary btn-block mb-2">
                        <i class="mdi mdi-cash-usd mr-1"></i> View Repayments
                    </a>
                    @if($loan->status === 'pending_review')
                    <form method="POST" action="{{ route('client.loans.cancel', $loan) }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-block"
                            onclick="return confirm('Cancel this loan application?')">
                            <i class="mdi mdi-close mr-1"></i> Cancel Application
                        </button>
                    </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
