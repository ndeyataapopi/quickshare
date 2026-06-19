@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">Disbursement Details</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.disbursements.index') }}">Disbursements</a></li>
                    <li class="breadcrumb-item active">{{ $loan->reference }}</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Loan #{{ $loan->reference }}</h5>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Borrower</div><div class="col-sm-8 font-weight-bold">{{ $loan->borrower->first_name }} {{ $loan->borrower->last_name }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Borrower Email</div><div class="col-sm-8">{{ $loan->borrower->email }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Approved Amount</div><div class="col-sm-8 font-weight-bold text-primary">N$ {{ number_format($loan->approved_amount ?? $loan->requested_amount, 2) }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Funded Amount</div><div class="col-sm-8">N$ {{ number_format($loan->funded_amount ?? 0, 2) }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Interest Rate</div><div class="col-sm-8">{{ $loan->interest_rate }}%</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Total Repayment</div><div class="col-sm-8">N$ {{ number_format($loan->total_repayment ?? 0, 2) }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Term</div><div class="col-sm-8">{{ $loan->loan_term_days }} days</div></div>
                    <div class="row mb-2">
                        <div class="col-sm-4 text-muted">Status</div>
                        <div class="col-sm-8">
                            @php $sc=['funded'=>'primary','disbursed'=>'info','active'=>'success']; @endphp
                            <span class="badge badge-{{ $sc[$loan->status] ?? 'secondary' }}">{{ ucfirst($loan->status) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            @if($loan->fundingTransactions && $loan->fundingTransactions->count())
            <div class="card">
                <div class="card-body">
                    <h6 class="text-uppercase font-weight-bold mb-3">Lender Contributions</h6>
                    <table class="table table-sm">
                        <thead><tr><th>Lender</th><th>Amount</th><th>Expected Return</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach($loan->fundingTransactions as $ft)
                            <tr>
                                <td>{{ $ft->lender ? $ft->lender->first_name . ' ' . $ft->lender->last_name : '—' }}</td>
                                <td>N$ {{ number_format($ft->amount, 2) }}</td>
                                <td>N$ {{ number_format($ft->expected_return, 2) }}</td>
                                <td><span class="badge badge-{{ $ft->status === 'confirmed' ? 'success' : 'warning' }}">{{ ucfirst($ft->status) }}</span></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Disbursement Actions</h5>
                    @if($loan->status === 'funded')
                    <div class="alert alert-info p-2 small">
                        Transfer N$ <strong>{{ number_format($loan->approved_amount ?? $loan->requested_amount, 2) }}</strong>
                        to borrower's bank account, then mark as disbursed.
                    </div>
                    <form method="POST" action="{{ route('admin.disbursements.disburse', $loan) }}" class="mb-2">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-block"
                            onclick="return confirm('Mark this loan as disbursed?')">
                            <i class="mdi mdi-send mr-1"></i> Mark as Disbursed
                        </button>
                    </form>
                    @elseif($loan->status === 'disbursed')
                    <div class="alert alert-warning p-2 small mb-2">
                        Confirm once funds have been successfully received by the borrower.
                    </div>
                    <form method="POST" action="{{ route('admin.disbursements.confirm', $loan) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn btn-success btn-block"
                            onclick="return confirm('Confirm disbursement and activate loan?')">
                            <i class="mdi mdi-check mr-1"></i> Confirm & Activate Loan
                        </button>
                    </form>
                    @else
                    <p class="text-muted small">No actions available for current status.</p>
                    @endif
                    <a href="{{ route('admin.disbursements.index') }}" class="btn btn-outline-secondary btn-block mt-2">
                        <i class="mdi mdi-arrow-left mr-1"></i> Back
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
