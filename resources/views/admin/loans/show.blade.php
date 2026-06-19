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
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.loans.index') }}">Loans</a></li>
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
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Reference</div><div class="col-sm-8 font-weight-bold">{{ $loan->reference }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Borrower</div><div class="col-sm-8 font-weight-bold">{{ $loan->borrower->first_name }} {{ $loan->borrower->last_name }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Requested Amount</div><div class="col-sm-8">N$ {{ number_format($loan->requested_amount, 2) }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Approved Amount</div><div class="col-sm-8">N$ {{ number_format($loan->approved_amount ?? 0, 2) }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Funded Amount</div><div class="col-sm-8">N$ {{ number_format($loan->funded_amount ?? 0, 2) }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Interest Rate</div><div class="col-sm-8">{{ $loan->interest_rate ?? '-' }}%</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Platform Fee</div><div class="col-sm-8">N$ {{ number_format($loan->platform_fee ?? 0, 2) }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Total Repayment</div><div class="col-sm-8 font-weight-bold">N$ {{ number_format($loan->total_repayment ?? 0, 2) }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Term</div><div class="col-sm-8">{{ $loan->loan_term_days }} days</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Purpose</div><div class="col-sm-8">{{ $loan->purpose }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Risk Score</div><div class="col-sm-8">{{ $loan->risk_score ?? '-' }}</div></div>
                    <div class="row mb-3">
                        <div class="col-sm-4 text-muted">Status</div>
                        <div class="col-sm-8">
                            @php $bm=['pending_review'=>'warning','marketplace'=>'info','partially_funded'=>'info','funded'=>'primary','disbursed'=>'primary','active'=>'primary','completed'=>'success','defaulted'=>'danger','cancelled'=>'secondary']; @endphp
                            <span class="badge badge-{{ $bm[$loan->status] ?? 'secondary' }}">{{ ucwords(str_replace('_', ' ', $loan->status)) }}</span>
                        </div>
                    </div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Submitted</div><div class="col-sm-8">{{ $loan->submitted_at ? $loan->submitted_at->format('M j, Y g:i A') : $loan->created_at->format('M j, Y') }}</div></div>
                    @if($loan->admin_notes)
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Admin Notes</div><div class="col-sm-8">{{ $loan->admin_notes }}</div></div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Admin Actions</h5>
                    @if(session('success'))
                        <div class="alert alert-success p-2">{{ session('success') }}</div>
                    @endif
                    @if($loan->status === 'pending_review')
                    <form method="POST" action="{{ route('admin.loans.update', $loan) }}">
                        @csrf @method('PUT')
                        <input type="hidden" name="decision" value="approve">
                        <div class="form-group">
                            <label class="small text-muted">Approved Amount (leave blank to use requested)</label>
                            <input type="number" name="approved_amount" class="form-control form-control-sm" step="0.01"
                                placeholder="{{ $loan->requested_amount }}" min="1">
                        </div>
                        <div class="form-group">
                            <label class="small text-muted">Notes</label>
                            <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Optional admin notes"></textarea>
                        </div>
                        <button type="submit" class="btn btn-success btn-block mb-2"
                            onclick="return confirm('Approve this loan?')">
                            <i class="mdi mdi-check mr-1"></i> Approve Loan
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.loans.update', $loan) }}">
                        @csrf @method('PUT')
                        <input type="hidden" name="decision" value="reject">
                        <div class="form-group">
                            <label class="small text-muted">Rejection Reason <span class="text-danger">*</span></label>
                            <textarea name="notes" class="form-control form-control-sm" rows="2"
                                placeholder="Reason for rejection" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger btn-block mb-2"
                            onclick="return confirm('Reject this loan?')">
                            <i class="mdi mdi-close mr-1"></i> Reject Loan
                        </button>
                    </form>
                    @endif
                    <a href="{{ route('admin.loans.index') }}" class="btn btn-outline-secondary btn-block mt-2">
                        <i class="mdi mdi-arrow-left mr-1"></i> Back to Loans
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
