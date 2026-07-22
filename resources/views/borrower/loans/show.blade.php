@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <div class="row page-titles">
        <div class="col-md-5 align-self-center"><h4 class="text-themecolor">Loan Details</h4></div>
        <div class="col-md-7 align-self-center text-right">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('client.loans.index') }}">My Loans</a></li>
                <li class="breadcrumb-item active">{{ $loan->reference }}</li>
            </ol>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-4">Loan #{{ $loan->reference }}</h5>
                    @php $sc=['active'=>'success','completed'=>'primary','pending_review'=>'warning','defaulted'=>'danger','cancelled'=>'secondary','marketplace'=>'info']; @endphp
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Status</div><div class="col-sm-8"><span class="badge badge-{{ $sc[$loan->status] ?? 'secondary' }} badge-pill px-3 py-2">{{ ucfirst(str_replace('_',' ',$loan->status)) }}</span></div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Requested Amount</div><div class="col-sm-8 font-weight-bold">N$ {{ number_format($loan->requested_amount) }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Approved Amount</div><div class="col-sm-8">N$ {{ number_format($loan->approved_amount ?? $loan->requested_amount) }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Funded Amount</div><div class="col-sm-8">N$ {{ number_format($loan->funded_amount ?? 0) }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Total Repayment</div><div class="col-sm-8">N$ {{ number_format($loan->total_repayment) }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Interest Rate</div><div class="col-sm-8">{{ $loan->interest_rate }}%</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Term</div><div class="col-sm-8">{{ $loan->loan_term_days }} days</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Repayment Date</div><div class="col-sm-8">{{ $loan->repayment_date ?? '-' }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Submitted</div><div class="col-sm-8">{{ $loan->submitted_at ? \Carbon\Carbon::parse($loan->submitted_at)->format('d M Y') : '-' }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Approved</div><div class="col-sm-8">{{ $loan->approved_at ? \Carbon\Carbon::parse($loan->approved_at)->format('d M Y') : '-' }}</div></div>
                    @if($loan->funded_amount > 0 && $loan->approved_amount > 0)
                    <hr>
                    <h6 class="font-weight-bold mb-2">Funding Progress</h6>
                    @php $pct = min(100, round($loan->funded_amount / $loan->approved_amount * 100)); @endphp
                    <div class="progress mb-2" style="height:12px">
                        <div class="progress-bar bg-info" style="width:{{ $pct }}%"></div>
                    </div>
                    <small class="text-muted">{{ $pct }}% funded (N$ {{ number_format($loan->funded_amount) }} of N$ {{ number_format($loan->approved_amount) }})</small>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Repayments</h5>
                    @php $repayments = $loan->repayments()->orderBy('due_date')->get(); @endphp
                    @if($repayments->count())
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Due</th><th>Amount</th><th>Status</th></tr></thead>
                            <tbody>
                            @foreach($repayments as $r)
                            <tr>
                                <td>{{ $r->due_date }}</td>
                                <td>N$ {{ number_format($r->amount) }}</td>
                                <td><span class="badge badge-{{ $r->status==='paid' ? 'success' : ($r->status==='overdue' ? 'danger' : 'warning') }}">{{ ucfirst($r->status) }}</span></td>
                            </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-muted small">No repayment records yet.</p>
                    @endif
                </div>
            </div>

            @if($loan->status === 'awaiting_disbursement')
                @php $pendingDisbursement = $loan->disbursements()->pendingBorrowerConfirmation()->latest()->first(); @endphp
                @if($pendingDisbursement)
                <div class="card mt-3">
                    <div class="card-body">
                        <h5 class="card-title text-uppercase">Confirm Disbursement</h5>
                        <div class="alert alert-info p-2 small mb-3">
                            The admin has sent <strong>N$ {{ number_format($pendingDisbursement->net_amount, 2) }}</strong> to your account.
                            Please confirm you have received the funds, or reject if you have not.
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-5 text-muted small">Reference</div>
                            <div class="col-sm-7 small font-weight-bold">{{ $pendingDisbursement->transaction_reference }}</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-5 text-muted small">Net Amount</div>
                            <div class="col-sm-7 small font-weight-bold text-primary">N$ {{ number_format($pendingDisbursement->net_amount, 2) }}</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-5 text-muted small">Payment Method</div>
                            <div class="col-sm-7 small">{{ ucfirst(str_replace('_', ' ', $pendingDisbursement->payment_method ?? 'bank_transfer')) }}</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-sm-5 text-muted small">External Ref</div>
                            <div class="col-sm-7 small">{{ $pendingDisbursement->external_reference ?: '—' }}</div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-5 text-muted small">Sent At</div>
                            <div class="col-sm-7 small">{{ $pendingDisbursement->processed_at ? $pendingDisbursement->processed_at->format('M j, Y H:i') : '—' }}</div>
                        </div>
                        <form method="POST" action="{{ route('client.loans.disbursement.confirm', $loan) }}" class="mb-2">
                            @csrf
                            <button type="submit" class="btn btn-success btn-block"
                                onclick="return confirm('Confirm you have received the disbursement? This will activate your loan.')">
                                <i class="mdi mdi-check-circle mr-1"></i> Confirm Receipt
                            </button>
                        </form>
                        <form method="POST" action="{{ route('client.loans.disbursement.reject', $loan) }}">
                            @csrf
                            <div class="form-group mb-2">
                                <textarea name="reason" class="form-control form-control-sm" rows="2" placeholder="Reason for rejection (optional)"></textarea>
                            </div>
                            <button type="submit" class="btn btn-danger btn-block"
                                onclick="return confirm('Are you sure you want to reject this disbursement? Admin will be notified.')">
                                <i class="mdi mdi-close-circle mr-1"></i> Reject Disbursement
                            </button>
                        </form>
                    </div>
                </div>
                @endif
            @endif
        </div>
    </div>
</div>
@endsection
