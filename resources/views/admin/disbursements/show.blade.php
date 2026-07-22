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
                            @php $sc=['funded'=>'primary','awaiting_disbursement'=>'info','pending_borrower_confirmation'=>'warning','disbursed'=>'info','active'=>'success']; @endphp
                            <span class="badge badge-{{ $sc[$loan->status] ?? 'secondary' }}">{{ ucfirst(str_replace('_', ' ', $loan->status)) }}</span>
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

            @php $disbursement = $loan->disbursements->sortByDesc('created_at')->first(); @endphp
            @if($disbursement)
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Disbursement Transaction</h5>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Reference</div><div class="col-sm-8 font-weight-bold">{{ $disbursement->transaction_reference }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Status</div><div class="col-sm-8"><span class="badge badge-{{ $disbursement->isAwaiting() ? 'info' : ($disbursement->isPendingBorrowerConfirmation() ? 'warning' : ($disbursement->isDisbursed() ? 'success' : ($disbursement->isFailed() ? 'danger' : 'secondary'))) }}">{{ ucfirst(str_replace('_', ' ', $disbursement->status)) }}</span></div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Gross Amount</div><div class="col-sm-8">N$ {{ number_format($disbursement->gross_amount, 2) }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Platform Fee</div><div class="col-sm-8">N$ {{ number_format($disbursement->platform_fee, 2) }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Net to Borrower</div><div class="col-sm-8 font-weight-bold text-primary">N$ {{ number_format($disbursement->net_amount, 2) }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Payment Method</div><div class="col-sm-8">{{ ucfirst(str_replace('_', ' ', $disbursement->payment_method ?? 'bank_transfer')) }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">External Reference</div><div class="col-sm-8">{{ $disbursement->external_reference ?: '—' }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Payment Proof</div><div class="col-sm-8">@if($disbursement->payment_proof_path)<a href="{{ route('admin.disbursements.show', $loan) }}" class="text-primary"><i class="mdi mdi-file-check"></i> View Proof</a>@else — @endif</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Processed At</div><div class="col-sm-8">{{ $disbursement->processed_at ? $disbursement->processed_at->format('M j, Y H:i') : '—' }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Borrower Confirmed</div><div class="col-sm-8">{{ $disbursement->borrower_confirmed_at ? $disbursement->borrower_confirmed_at->format('M j, Y H:i') : '—' }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Reconciled</div><div class="col-sm-8">{{ $disbursement->reconciled_at ? $disbursement->reconciled_at->format('M j, Y H:i') . ' by ' . $disbursement->reconciled_by : '—' }}</div></div>

                    @if($disbursement->ledger_entries)
                    <h6 class="text-uppercase font-weight-bold mt-4 mb-3">Ledger Entries</h6>
                    <table class="table table-sm">
                        <thead><tr><th>Account</th><th>Debit</th><th>Credit</th><th>Description</th></tr></thead>
                        <tbody>
                            @foreach($disbursement->ledger_entries as $entry)
                            <tr>
                                <td>{{ $entry['account'] ?? '—' }}</td>
                                <td>N$ {{ number_format($entry['debit'] ?? 0, 2) }}</td>
                                <td>N$ {{ number_format($entry['credit'] ?? 0, 2) }}</td>
                                <td>{{ $entry['description'] ?? '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif
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
                        Create a disbursement transaction for N$ <strong>{{ number_format($loan->approved_amount ?? $loan->requested_amount, 2) }}</strong>.
                        The borrower will receive the net amount after the platform fee.
                    </div>
                    <form method="POST" action="{{ route('admin.disbursements.disburse', $loan) }}" class="mb-2">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-block"
                            onclick="return confirm('Initiate disbursement and record ledger entries?')">
                            <i class="mdi mdi-send mr-1"></i> Initiate Disbursement
                        </button>
                    </form>
                    @elseif($loan->status === 'awaiting_disbursement')
                    <div class="alert alert-warning p-2 small mb-2">
                        Record the outgoing payment details below. The borrower will be notified to confirm receipt.
                    </div>
                    <form method="POST" action="{{ route('admin.disbursements.confirm', $loan) }}" enctype="multipart/form-data">
                        @csrf @method('PATCH')
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold">Payment Method</label>
                            <select name="payment_method" class="form-control form-control-sm" required>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="eft">EFT</option>
                                <option value="wallet">Wallet</option>
                                <option value="cash">Cash</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold">Reference Number</label>
                            <input type="text" name="external_reference" class="form-control form-control-sm" placeholder="e.g. TRX-12345678" required>
                        </div>
                        <div class="form-group mb-2">
                            <label class="small font-weight-bold">Payment Proof (PDF/JPG/PNG, max 5MB)</label>
                            <input type="file" name="payment_proof" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png" required>
                        </div>
                        <button type="submit" class="btn btn-success btn-block"
                            onclick="return confirm('Record outgoing disbursement? Borrower will be notified to confirm receipt.')">
                            <i class="mdi mdi-send-check mr-1"></i> Record Outgoing Disbursement
                        </button>
                    </form>
                    @elseif($loan->status === 'pending_borrower_confirmation')
                    <div class="alert alert-info p-2 small mb-2">
                        Outgoing disbursement recorded. Waiting for borrower to confirm receipt.
                    </div>
                    @elseif($loan->status === 'active')
                    <div class="alert alert-success p-2 small mb-2">
                        Disbursement has been processed and the loan is active.
                    </div>
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
