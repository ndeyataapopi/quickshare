@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">Funding Transaction</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.funding-payments.index') }}">Funding Payments</a></li>
                    <li class="breadcrumb-item active">{{ $transaction->transaction_reference }}</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-4">Transaction #{{ $transaction->transaction_reference }}</h5>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Reference</div><div class="col-sm-8 font-weight-bold">{{ $transaction->transaction_reference }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Lender</div><div class="col-sm-8">{{ $transaction->lender ? $transaction->lender->first_name . ' ' . $transaction->lender->last_name : '—' }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Loan Reference</div><div class="col-sm-8">{{ $transaction->loan ? $transaction->loan->reference : '—' }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Borrower</div><div class="col-sm-8">{{ $transaction->loan && $transaction->loan->borrower ? $transaction->loan->borrower->first_name . ' ' . $transaction->loan->borrower->last_name : '—' }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Amount</div><div class="col-sm-8 font-weight-bold">N$ {{ number_format($transaction->amount, 2) }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Interest Rate</div><div class="col-sm-8">{{ $transaction->interest_rate }}%</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Expected Return</div><div class="col-sm-8 text-success font-weight-bold">N$ {{ number_format($transaction->expected_return, 2) }}</div></div>
                    <div class="row mb-2">
                        <div class="col-sm-4 text-muted">Status</div>
                        <div class="col-sm-8">
                            @php $sc=['pending'=>'warning','confirmed'=>'success','rejected'=>'danger','cancelled'=>'secondary','refunded'=>'secondary']; @endphp
                            <span class="badge badge-{{ $sc[$transaction->status] ?? 'secondary' }}">{{ ucfirst($transaction->status) }}</span>
                        </div>
                    </div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Payment Method</div><div class="col-sm-8">{{ ucfirst(str_replace('_', ' ', $transaction->payment_method ?? '—')) }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Payment Reference</div><div class="col-sm-8">{{ $transaction->payment_reference ?? '—' }}</div></div>
                    @if($transaction->payment_date)
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Payment Date</div><div class="col-sm-8">{{ \Carbon\Carbon::parse($transaction->payment_date)->format('M j, Y') }}</div></div>
                    @endif
                    @if(($transaction->metadata['payer_reference_number'] ?? null) || ($transaction->metadata['payer_transaction_number'] ?? null))
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Payer Reference #</div><div class="col-sm-8">{{ $transaction->metadata['payer_reference_number'] ?? '—' }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Payer Transaction #</div><div class="col-sm-8">{{ $transaction->metadata['payer_transaction_number'] ?? '—' }}</div></div>
                    @endif
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Created</div><div class="col-sm-8">{{ $transaction->created_at->format('M j, Y g:i A') }}</div></div>
                    @if($transaction->confirmed_at)
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Confirmed At</div><div class="col-sm-8">{{ $transaction->confirmed_at->format('M j, Y g:i A') }}</div></div>
                    @endif
                    @if($transaction->rejected_at)
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Rejected At</div><div class="col-sm-8">{{ $transaction->rejected_at->format('M j, Y g:i A') }}</div></div>
                    @endif
                    @if($transaction->admin_notes)
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Admin Notes</div><div class="col-sm-8 small">{{ $transaction->admin_notes }}</div></div>
                    @endif
                    @if($transaction->notes)
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Lender Notes</div><div class="col-sm-8 small">{{ $transaction->notes }}</div></div>
                    @endif
                    @if($transaction->payment_proof_path)
                    <div class="row mb-2">
                        <div class="col-sm-4 text-muted">Proof of Payment</div>
                        <div class="col-sm-8">
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($transaction->payment_proof_path) }}" target="_blank" class="btn btn-sm btn-info">
                                <i class="mdi mdi-file-pdf mr-1"></i> View Proof
                            </a>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Actions</h5>
                    @if($transaction->status === 'pending')
                    <form method="POST" action="{{ route('admin.funding-payments.confirm', $transaction) }}" class="mb-2">
                        @csrf
                        <button type="submit" class="btn btn-success btn-block"
                            onclick="return confirm('Confirm this payment and apply it to the loan?')">
                            <i class="mdi mdi-check mr-1"></i> Approve Payment
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.funding-payments.reject', $transaction) }}" class="mb-2">
                        @csrf
                        <div class="form-group">
                            <textarea name="reason" class="form-control" rows="2" placeholder="Rejection reason (required)"></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger btn-block"
                            onclick="return confirm('Reject this payment and notify the lender?')">
                            <i class="mdi mdi-close mr-1"></i> Reject Payment
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.funding-payments.request-info', $transaction) }}" class="mb-2">
                        @csrf
                        <div class="form-group">
                            <textarea name="message" class="form-control" rows="2" placeholder="Request more information"></textarea>
                        </div>
                        <button type="submit" class="btn btn-info btn-block">
                            <i class="mdi mdi-information mr-1"></i> Request More Info
                        </button>
                    </form>
                    @endif
                    <a href="{{ route('admin.funding-payments.index') }}" class="btn btn-outline-secondary btn-block mt-2">
                        <i class="mdi mdi-arrow-left mr-1"></i> Back to Funding Payments
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
