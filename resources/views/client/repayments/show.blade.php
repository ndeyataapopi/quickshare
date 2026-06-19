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
                    <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('client.repayments.index') }}">Repayments</a></li>
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
                    <h5 class="card-title text-uppercase mb-4">Repayment Details</h5>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Amount</div><div class="col-sm-8 font-weight-bold">N\$ {{ number_format($repayment->amount) }}</div></div>
                    @if($repayment->loan)
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Loan</div><div class="col-sm-8"><a href="{{ route('client.loans.show', $repayment->loan) }}">{{ $repayment->loan->reference }}</a></div></div>
                    @endif
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Due Date</div><div class="col-sm-8">{{ optional($repayment->due_date)->format('M j, Y') ?? '-' }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Paid On</div><div class="col-sm-8">{{ optional($repayment->paid_at)->format('M j, Y') ?? '-' }}</div></div>
                    <div class="row mb-3">
                        <div class="col-sm-4 text-muted">Status</div>
                        <div class="col-sm-8">
                            @php $sc=['completed'=>'success','overdue'=>'danger','pending'=>'warning']; @endphp
                            <span class="badge badge-{{ $sc[$repayment->status] ?? 'secondary' }}">{{ ucfirst($repayment->status) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Quick Actions</h5>
                    <a href="{{ route('client.repayments.index') }}" class="btn btn-outline-secondary btn-block mb-2"><i class="mdi mdi-arrow-left"></i> Back to Repayments</a>
                    @if($repayment->loan)
                    <a href="{{ route('client.loans.show', $repayment->loan) }}" class="btn btn-outline-primary btn-block"><i class="mdi mdi-cash mr-1"></i> View Loan</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
