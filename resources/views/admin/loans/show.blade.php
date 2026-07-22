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
                    <a href="{{ route('admin.loans.agreement', $loan) }}" class="btn btn-outline-info btn-block mt-2" @if(! $loan->agreement_path) disabled @endif>
                        <i class="mdi mdi-file-document-outline mr-1"></i> View Agreement
                    </a>
                    <a href="{{ route('admin.loans.index') }}" class="btn btn-outline-secondary btn-block mt-2">
                        <i class="mdi mdi-arrow-left mr-1"></i> Back to Loans
                    </a>
                </div>
            </div>
        </div>
    </div>

    @if(isset($financialSummary))
    <div class="row mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Financial Summary &amp; Reconciliation</h5>

                    <div class="row mb-4">
                        <div class="col-md-3"><div class="card bg-light"><div class="card-body py-2">
                            <small class="text-muted d-block">Funding Received</small>
                            <h6 class="mb-0">N$ {{ number_format($financialSummary['funding_summary']['total_received'], 2) }}</h6>
                        </div></div></div>
                        <div class="col-md-3"><div class="card bg-light"><div class="card-body py-2">
                            <small class="text-muted d-block">Disbursed to Borrower</small>
                            <h6 class="mb-0">N$ {{ number_format(collect($financialSummary['borrower_disbursement'])->where('status','disbursed')->sum('net_amount'), 2) }}</h6>
                        </div></div></div>
                        <div class="col-md-3"><div class="card bg-light"><div class="card-body py-2">
                            <small class="text-muted d-block">Repaid by Borrower</small>
                            <h6 class="mb-0">N$ {{ number_format($financialSummary['repayment_summary']['actual_repaid'], 2) }}</h6>
                        </div></div></div>
                        <div class="col-md-3"><div class="card bg-light"><div class="card-body py-2">
                            <small class="text-muted d-block">Outstanding Balance</small>
                            <h6 class="mb-0">N$ {{ number_format($financialSummary['repayment_summary']['outstanding_balance'], 2) }}</h6>
                        </div></div></div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-3"><div class="card bg-light"><div class="card-body py-2">
                            <small class="text-muted d-block">Platform Fee</small>
                            <h6 class="mb-0">N$ {{ number_format($financialSummary['platform_summary']['platform_fee_earned'], 2) }}</h6>
                        </div></div></div>
                        <div class="col-md-3"><div class="card bg-light"><div class="card-body py-2">
                            <small class="text-muted d-block">Penalties Collected</small>
                            <h6 class="mb-0">N$ {{ number_format($financialSummary['platform_summary']['penalties_collected'], 2) }}</h6>
                        </div></div></div>
                        <div class="col-md-3"><div class="card bg-light"><div class="card-body py-2">
                            <small class="text-muted d-block">Net Platform Revenue</small>
                            <h6 class="mb-0">N$ {{ number_format($financialSummary['platform_summary']['net_platform_revenue'], 2) }}</h6>
                        </div></div></div>
                        <div class="col-md-3"><div class="card bg-light"><div class="card-body py-2">
                            <small class="text-muted d-block">Investors</small>
                            <h6 class="mb-0">{{ $financialSummary['funding_summary']['investor_count'] }}</h6>
                        </div></div></div>
                    </div>

                    <h6 class="text-uppercase text-muted mb-2">Reconciliation</h6>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered">
                            <tbody>
                                <tr>
                                    <td class="text-right font-weight-bold" style="width:25%">Money In</td>
                                    <td>N$ {{ number_format($financialSummary['reconciliation']['money_in'], 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-right font-weight-bold">Money Out</td>
                                    <td>N$ {{ number_format($financialSummary['reconciliation']['money_out'], 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-right font-weight-bold">Platform Revenue</td>
                                    <td>N$ {{ number_format($financialSummary['reconciliation']['platform_revenue'], 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-right font-weight-bold">Money Out + Revenue</td>
                                    <td>N$ {{ number_format($financialSummary['reconciliation']['money_out_plus_revenue'], 2) }}</td>
                                </tr>
                                <tr class="{{ $financialSummary['reconciliation']['reconciled'] ? 'table-success' : 'table-danger' }}">
                                    <td class="text-right font-weight-bold">Status</td>
                                    <td>
                                        @if($financialSummary['reconciliation']['reconciled'])
                                            <span class="badge badge-success">RECONCILED</span>
                                        @else
                                            <span class="badge badge-danger">DISCREPANCY DETECTED</span>
                                        @endif
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h6 class="text-uppercase text-muted mb-2">Reconciliation Checks</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Check</th>
                                    <th>Status</th>
                                    <th>Detail</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($financialSummary['reconciliation']['checks'] as $check)
                                <tr class="{{ $check['passed'] ? '' : 'table-warning' }}">
                                    <td>{{ $check['label'] }}</td>
                                    <td>
                                        @if($check['passed'])
                                            <span class="badge badge-success">PASS</span>
                                        @else
                                            <span class="badge badge-danger">FAIL</span>
                                        @endif
                                    </td>
                                    <td><small>{{ $check['detail'] }}</small></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Funding Summary</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead class="thead-light">
                                <tr>
                                    <th>Lender</th>
                                    <th class="text-right">Amount</th>
                                    <th class="text-right">Interest Rate</th>
                                    <th class="text-right">Expected Return</th>
                                    <th class="text-right">Funding %</th>
                                    <th>Reference</th>
                                    <th>Confirmed At</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($financialSummary['funding_summary']['contributions'] as $contribution)
                                <tr>
                                    <td>{{ $contribution['lender_name'] }}</td>
                                    <td class="text-right">N$ {{ number_format($contribution['amount'], 2) }}</td>
                                    <td class="text-right">{{ $contribution['interest_rate'] }}%</td>
                                    <td class="text-right">N$ {{ number_format($contribution['expected_return'], 2) }}</td>
                                    <td class="text-right">{{ $contribution['funding_percentage'] }}%</td>
                                    <td><small>{{ $contribution['transaction_reference'] }}</small></td>
                                    <td>{{ $contribution['confirmed_at'] ?? '-' }}</td>
                                    <td><span class="badge badge-success">{{ $contribution['status'] }}</span></td>
                                </tr>
                                @endforeach
                                @if(count($financialSummary['funding_summary']['contributions']) === 0)
                                <tr><td colspan="8" class="text-center text-muted">No confirmed funding yet</td></tr>
                                @endif
                            </tbody>
                            <tfoot>
                                <tr class="font-weight-bold">
                                    <td>Total Received</td>
                                    <td class="text-right">N$ {{ number_format($financialSummary['funding_summary']['total_received'], 2) }}</td>
                                    <td colspan="2">Target: N$ {{ number_format($financialSummary['funding_summary']['target_amount'], 2) }}</td>
                                    <td class="text-right">{{ $financialSummary['funding_summary']['progress_percent'] }}%</td>
                                    <td colspan="3">Remaining: N$ {{ number_format($financialSummary['funding_summary']['remaining'], 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Incoming Funds</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead class="thead-light">
                                <tr>
                                    <th>Type</th>
                                    <th>Lender</th>
                                    <th class="text-right">Amount</th>
                                    <th>Payment Date</th>
                                    <th>Transaction Ref</th>
                                    <th>Payment Ref</th>
                                    <th>Confirmed At</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($financialSummary['incoming_funds'] as $fund)
                                <tr>
                                    <td><span class="badge badge-info">{{ $fund['type'] }}</span></td>
                                    <td>{{ $fund['lender_name'] }}</td>
                                    <td class="text-right">N$ {{ number_format($fund['amount'], 2) }}</td>
                                    <td>{{ $fund['payment_date'] ?? '-' }}</td>
                                    <td><small>{{ $fund['transaction_reference'] }}</small></td>
                                    <td><small>{{ $fund['payment_reference'] ?? '-' }}</small></td>
                                    <td>{{ $fund['confirmed_at'] ?? '-' }}</td>
                                    <td>
                                        @if($fund['status'] === 'confirmed')
                                            <span class="badge badge-success">{{ $fund['status'] }}</span>
                                        @else
                                            <span class="badge badge-warning">{{ $fund['status'] }}</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                                @if(count($financialSummary['incoming_funds']) === 0)
                                <tr><td colspan="8" class="text-center text-muted">No incoming funds</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Borrower Disbursement</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead class="thead-light">
                                <tr>
                                    <th class="text-right">Gross Amount</th>
                                    <th class="text-right">Platform Fee</th>
                                    <th class="text-right">Net Amount</th>
                                    <th>Date</th>
                                    <th>Payment Method</th>
                                    <th>Transaction Ref</th>
                                    <th>External Ref</th>
                                    <th>Borrower Confirmed</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($financialSummary['borrower_disbursement'] as $disb)
                                <tr>
                                    <td class="text-right">N$ {{ number_format($disb['gross_amount'], 2) }}</td>
                                    <td class="text-right">N$ {{ number_format($disb['platform_fee'], 2) }}</td>
                                    <td class="text-right font-weight-bold">N$ {{ number_format($disb['net_amount'], 2) }}</td>
                                    <td>{{ $disb['date'] ?? '-' }}</td>
                                    <td>{{ $disb['payment_method'] ?? '-' }}</td>
                                    <td><small>{{ $disb['transaction_reference'] }}</small></td>
                                    <td><small>{{ $disb['external_reference'] ?? '-' }}</small></td>
                                    <td>{{ $disb['borrower_confirmed_at'] ?? '-' }}</td>
                                    <td><span class="badge badge-success">{{ $disb['status'] }}</span></td>
                                </tr>
                                @endforeach
                                @if(count($financialSummary['borrower_disbursement']) === 0)
                                <tr><td colspan="9" class="text-center text-muted">No disbursement processed yet</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Repayment Summary</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th>
                                    <th class="text-right">Amount</th>
                                    <th class="text-right">Principal</th>
                                    <th class="text-right">Interest</th>
                                    <th class="text-right">Platform Fee</th>
                                    <th class="text-right">Penalty</th>
                                    <th>Due Date</th>
                                    <th>Paid Date</th>
                                    <th>Reference</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($financialSummary['repayment_summary']['repayments'] as $repayment)
                                <tr>
                                    <td>{{ $repayment['id'] }}</td>
                                    <td class="text-right">N$ {{ number_format($repayment['amount'], 2) }}</td>
                                    <td class="text-right">N$ {{ number_format($repayment['principal'], 2) }}</td>
                                    <td class="text-right">N$ {{ number_format($repayment['interest'], 2) }}</td>
                                    <td class="text-right">N$ {{ number_format($repayment['platform_fee'], 2) }}</td>
                                    <td class="text-right">N$ {{ number_format($repayment['penalty'], 2) }}</td>
                                    <td>{{ $repayment['due_date'] ?? '-' }}</td>
                                    <td>{{ $repayment['paid_date'] ?? '-' }}</td>
                                    <td><small>{{ $repayment['transaction_reference'] }}</small></td>
                                    <td>
                                        @php $rm=['pending'=>'warning','paid'=>'success','overdue'=>'danger','defaulted'=>'danger']; @endphp
                                        <span class="badge badge-{{ $rm[$repayment['status']] ?? 'secondary' }}">{{ $repayment['status'] }}</span>
                                    </td>
                                </tr>
                                @endforeach
                                @if(count($financialSummary['repayment_summary']['repayments']) === 0)
                                <tr><td colspan="10" class="text-center text-muted">No repayment schedule created yet</td></tr>
                                @endif
                            </tbody>
                            <tfoot>
                                <tr class="font-weight-bold">
                                    <td>Totals</td>
                                    <td class="text-right">N$ {{ number_format($financialSummary['repayment_summary']['scheduled_total'], 2) }}</td>
                                    <td colspan="4">
                                        Paid: N$ {{ number_format($financialSummary['repayment_summary']['actual_repaid'], 2) }} |
                                        Outstanding: N$ {{ number_format($financialSummary['repayment_summary']['outstanding_balance'], 2) }} |
                                        Penalties: N$ {{ number_format($financialSummary['repayment_summary']['total_penalties'], 2) }}
                                    </td>
                                    <td colspan="3">{{ $financialSummary['repayment_summary']['paid_count'] }} / {{ $financialSummary['repayment_summary']['repayment_count'] }} paid</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Lender Distribution</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead class="thead-light">
                                <tr>
                                    <th>Lender</th>
                                    <th class="text-right">Invested</th>
                                    <th class="text-right">Expected Return</th>
                                    <th class="text-right">Actual Return</th>
                                    <th class="text-right">Principal Returned</th>
                                    <th class="text-right">Interest Earned</th>
                                    <th class="text-right">Total Paid</th>
                                    <th>Funded At</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($financialSummary['lender_distribution'] as $lender)
                                <tr>
                                    <td>{{ $lender['lender_name'] }}</td>
                                    <td class="text-right">N$ {{ number_format($lender['invested_amount'], 2) }}</td>
                                    <td class="text-right">N$ {{ number_format($lender['expected_return'], 2) }}</td>
                                    <td class="text-right">N$ {{ number_format($lender['actual_return'], 2) }}</td>
                                    <td class="text-right">N$ {{ number_format($lender['principal_returned'], 2) }}</td>
                                    <td class="text-right">N$ {{ number_format($lender['interest_earned'], 2) }}</td>
                                    <td class="text-right font-weight-bold">N$ {{ number_format($lender['total_paid'], 2) }}</td>
                                    <td>{{ $lender['funded_at'] ?? '-' }}</td>
                                    <td><span class="badge badge-info">{{ $lender['investment_status'] }}</span></td>
                                </tr>
                                @endforeach
                                @if(count($financialSummary['lender_distribution']) === 0)
                                <tr><td colspan="9" class="text-center text-muted">No lender investments yet</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
