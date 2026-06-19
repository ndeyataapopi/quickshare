@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">Repayment Details</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.repayments.index') }}">Repayments</a></li>
                    <li class="breadcrumb-item active">Repayment #{{ $repayment->id }}</li>
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
                    <h5 class="card-title text-uppercase mb-4">Repayment Details</h5>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Loan</div><div class="col-sm-8">{{ $repayment->loan ? $repayment->loan->reference : '—' }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Borrower</div><div class="col-sm-8 font-weight-bold">{{ $repayment->loan && $repayment->loan->borrower ? $repayment->loan->borrower->first_name . ' ' . $repayment->loan->borrower->last_name : '—' }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Amount</div><div class="col-sm-8 font-weight-bold">N$ {{ number_format($repayment->amount, 2) }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Due Date</div><div class="col-sm-8">{{ $repayment->due_date ? \Carbon\Carbon::parse($repayment->due_date)->format('M j, Y') : '—' }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Paid At</div><div class="col-sm-8">{{ $repayment->paid_at ? \Carbon\Carbon::parse($repayment->paid_at)->format('M j, Y g:i A') : 'Not yet paid' }}</div></div>
                    <div class="row mb-2">
                        <div class="col-sm-4 text-muted">Status</div>
                        <div class="col-sm-8">
                            @php $sc=['pending'=>'warning','completed'=>'success','overdue'=>'danger']; @endphp
                            <span class="badge badge-{{ $sc[$repayment->status] ?? 'secondary' }}">{{ ucfirst($repayment->status) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            @if($repayment->loan && $repayment->loan->fundingTransactions && $repayment->loan->fundingTransactions->count())
            <div class="card">
                <div class="card-body">
                    <h6 class="text-uppercase font-weight-bold mb-3">Lender Allocations</h6>
                    <p class="text-muted small">Repayment will be distributed to these lenders proportionally:</p>
                    <table class="table table-sm">
                        <thead><tr><th>Lender</th><th>Investment</th><th>Share %</th></tr></thead>
                        <tbody>
                            @php $totalFunded = $repayment->loan->funded_amount ?: 1; @endphp
                            @foreach($repayment->loan->fundingTransactions->where('status', 'confirmed') as $ft)
                            <tr>
                                <td>{{ $ft->lender ? $ft->lender->first_name . ' ' . $ft->lender->last_name : '—' }}</td>
                                <td>N$ {{ number_format($ft->amount, 2) }}</td>
                                <td>{{ number_format($ft->amount / $totalFunded * 100, 1) }}%</td>
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
                    <h5 class="card-title text-uppercase mb-3">Actions</h5>
                    @if($repayment->status === 'pending')
                    <form method="POST" action="{{ route('admin.repayments.confirm', $repayment) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn btn-success btn-block"
                            onclick="return confirm('Confirm this repayment?')">
                            <i class="mdi mdi-check mr-1"></i> Confirm Repayment
                        </button>
                    </form>
                    @endif
                    <a href="{{ route('admin.repayments.index') }}" class="btn btn-outline-secondary btn-block mt-2">
                        <i class="mdi mdi-arrow-left mr-1"></i> Back to Repayments
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
