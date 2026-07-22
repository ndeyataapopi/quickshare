@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0"><i class="mdi mdi-cash-usd mr-2"></i>Repayments</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">Repayments</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show"><i class="mdi mdi-check-circle mr-2"></i>{{ session('success') }}<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>
    @endif
    
    <!-- Payment Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-primary mb-0">{{ formatKpi($repayments->count()) }}</h4>
                    <small class="text-muted">Total Repayments</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-success mb-0">{{ formatKpi($repayments->where('status', 'paid')->count()) }}</h4>
                    <small class="text-muted">Paid</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-warning mb-0">{{ formatKpi($repayments->where('status', 'pending')->count()) }}</h4>
                    <small class="text-muted">Pending</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-danger mb-0">{{ formatKpi($repayments->where('status', 'overdue')->count()) }}</h4>
                    <small class="text-muted">Overdue</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Submit Repayment Section -->
    @if($repayments->whereIn('status', ['pending', 'partial', 'overdue'])->count() > 0)
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-success">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="mdi mdi-credit-card mr-2"></i>Submit Repayment</h6>
                </div>
                <div class="card-body">
                    <p class="mb-3">You have {{ $repayments->whereIn('status', ['pending', 'partial', 'overdue'])->count() }} repayment(s) due. Click below to submit a repayment request.</p>
                    <a href="{{ route('client.repayments.create') }}" class="btn btn-success">
                        <i class="mdi mdi-credit-card-plus mr-2"></i>Submit Repayment Request
                    </a>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title text-uppercase mb-0">Payment History</h5>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-primary filter-btn active" data-filter="all">All</button>
                            <button class="btn btn-sm btn-outline-success filter-btn" data-filter="paid">Paid</button>
                            <button class="btn btn-sm btn-outline-warning filter-btn" data-filter="pending">Pending</button>
                            <button class="btn btn-sm btn-outline-info filter-btn" data-filter="pending_approval">Pending Approval</button>
                            <button class="btn btn-sm btn-outline-danger filter-btn" data-filter="overdue">Overdue</button>
                        </div>
                    </div>
                    @if($repayments->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th><th>Loan</th><th>Amount</th><th>Due Date</th><th>Status</th><th>Paid On</th><th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($repayments as $repayment)
                                <tr data-status="{{ $repayment->status }}">
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        <div>
                                            <strong>{{ $repayment->loan ? $repayment->loan->reference : '#' . $repayment->loan_id }}</strong>
                                            @if($repayment->loan && $repayment->loan->purpose)
                                                <br><small class="text-muted">{{ $repayment->loan->purpose }}</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <strong>{{ kpiMoney($repayment->amount) }}</strong>
                                        @if($repayment->status === 'overdue')
                                            <br><small class="text-danger">+ Late fees may apply</small>
                                        @endif
                                    </td>
                                    <td>
                                        <div>
                                            {{ optional($repayment->due_date)->format('M j, Y') ?? '-' }}
                                            @if($repayment->due_date && $repayment->due_date->isPast() && $repayment->status !== 'paid')
                                                <br><small class="text-danger">{{ $repayment->due_date->diffForHumans() }}</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @php 
                                            $statusColors = [
                                                'paid' => 'success', 
                                                'overdue' => 'danger', 
                                                'pending' => 'warning', 
                                                'defaulted' => 'danger',
                                                'pending_approval' => 'info',
                                                'partial' => 'primary',
                                            ]; 
                                            $statusIcons = [
                                                'paid' => 'check-circle', 
                                                'overdue' => 'alert-circle', 
                                                'pending' => 'clock', 
                                                'defaulted' => 'alert-circle',
                                                'pending_approval' => 'hourglass',
                                                'partial' => 'clock',
                                            ]; 
                                        @endphp
                                        <span class="badge badge-{{ $statusColors[$repayment->status] ?? 'secondary' }}">
                                            <i class="mdi mdi-{{ $statusIcons[$repayment->status] ?? 'help-circle' }} mr-1"></i>
                                            {{ ucfirst($repayment->status) }}
                                        </span>
                                    </td>
                                    <td>{{ optional($repayment->paid_at)->format('M j, Y g:i A') ?? '-' }}</td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="{{ route('client.repayments.show', $repayment) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="mdi mdi-eye"></i>
                                            </a>
                                            @if(in_array($repayment->status, ['pending', 'partial', 'overdue']))
                                                <a href="{{ route('client.repayments.create', ['loan_id' => $repayment->loan_id]) }}" class="btn btn-sm btn-outline-success">
                                                    <i class="mdi mdi-credit-card"></i>
                                                </a>
                                            @endif
                                            @if($repayment->status === 'paid')
                                                <button class="btn btn-sm btn-outline-info download-receipt" data-id="{{ $repayment->id }}">
                                                    <i class="mdi mdi-download"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $repayments->links() }}</div>
                    @else
                    <div class="text-center py-5">
                        <i class="mdi mdi-cash-usd text-muted" style="font-size:64px;"></i>
                        <h5 class="mt-3 text-muted">No Repayments Yet</h5>
                        <p class="text-muted">Your loan repayments will appear here.</p>
                        <a href="{{ route('client.loans.index') }}" class="btn btn-primary">View My Loans</a>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Make Payment</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Payment Details</h6>
                        <div class="form-group">
                            <label>Repayment Amount</label>
                            <input type="text" class="form-control" id="paymentAmount" readonly>
                        </div>
                        <div class="form-group">
                            <label>Payment Method</label>
                            <select class="form-control" id="paymentMethod">
                                <option value="">Select payment method</option>
                                <option value="bank">Bank Transfer</option>
                                <option value="card">Credit/Debit Card</option>
                                <option value="mobile">Mobile Money</option>
                                <option value="cash">Cash Deposit</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Payment Instructions</h6>
                        <div id="paymentInstructions" class="alert alert-info">
                            <i class="mdi mdi-information mr-2"></i>
                            Select a payment method to see instructions.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmPayment">
                    <i class="mdi mdi-check mr-2"></i>Confirm Payment
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Filter functionality
    const filterButtons = document.querySelectorAll('.filter-btn');
    const tableRows = document.querySelectorAll('tbody tr[data-status]');
    
    filterButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            // Update active button
            filterButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            const filter = this.dataset.filter;
            
            // Filter rows
            tableRows.forEach(row => {
                if (filter === 'all' || row.dataset.status === filter) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });
    
    // Payment method selection
    const paymentMethodButtons = document.querySelectorAll('.payment-method');
    const paymentModal = document.getElementById('paymentModal');
    const paymentAmount = document.getElementById('paymentAmount');
    const paymentMethod = document.getElementById('paymentMethod');
    const paymentInstructions = document.getElementById('paymentInstructions');
    
    const paymentInstructionsText = {
        bank: 'Please transfer the amount to:<br><strong>Bank: First National Bank</strong><br>Account: 1234567890<br>Reference: REPAY-{id}<br><small class="text-muted">Payments may take 1-2 business days to reflect.</small>',
        card: 'You will be redirected to our secure payment gateway to complete your card payment.<br><small class="text-muted">Instant processing - 2.5% processing fee applies.</small>',
        mobile: 'Please send the amount via mobile money:<br><strong>Number: +264 81 123 4567</strong><br>Reference: REPAY-{id}<br><small class="text-muted">Instant processing.</small>',
        cash: 'Please visit any of our branches to make a cash deposit.<br><small class="text-muted">Bring your ID and loan reference number.</small>'
    };
    
    paymentMethodButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const method = this.dataset.method;
            paymentMethod.value = method;
            paymentInstructions.innerHTML = `<i class="mdi mdi-information mr-2"></i>${paymentInstructionsText[method]}`;
            paymentModal.modal('show');
        });
    });
    
    // Pay now buttons
    const payNowButtons = document.querySelectorAll('.pay-now');
    let currentRepaymentId = null;
    
    payNowButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            currentRepaymentId = this.dataset.id;
            const amount = this.dataset.amount;
            paymentAmount.value = '{{ config("loans.currency_symbol") }}' + parseFloat(amount).toLocaleString();
            paymentModal.modal('show');
        });
    });
    
    // Payment method change
    paymentMethod.addEventListener('change', function() {
        const method = this.value;
        if (method && paymentInstructionsText[method]) {
            paymentInstructions.innerHTML = `<i class="mdi mdi-information mr-2"></i>${paymentInstructionsText[method].replace('{id}', currentRepaymentId || 'XXX')}`;
        }
    });
    
    // Confirm payment
    document.getElementById('confirmPayment').addEventListener('click', function() {
        const method = paymentMethod.value;
        if (!method) {
            alert('Please select a payment method');
            return;
        }
        
        // Show loading state
        this.disabled = true;
        this.innerHTML = '<i class="mdi mdi-loading mdi-spin mr-2"></i>Processing...';
        
        // Simulate payment processing
        setTimeout(() => {
            alert('Payment instructions have been sent to your email. Please complete the payment using the selected method.');
            paymentModal.modal('hide');
            this.disabled = false;
            this.innerHTML = '<i class="mdi mdi-check mr-2"></i>Confirm Payment';
        }, 2000);
    });
    
    // Download receipt functionality
    const downloadButtons = document.querySelectorAll('.download-receipt');
    downloadButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const repaymentId = this.dataset.id;
            // Simulate download
            const link = document.createElement('a');
            link.href = '#';
            link.download = `receipt-${repaymentId}.pdf`;
            link.click();
            alert('Receipt download started. Check your downloads folder.');
        });
    });
    
    // Auto-select first payment method if pending payments exist
    if (document.querySelector('.payment-method')) {
        document.querySelector('.payment-method').click();
    }
});
</script>
@endsection
