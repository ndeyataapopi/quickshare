@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0"><i class="mdi mdi-cash-usd mr-2"></i>Submit Repayment</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('client.repayments.index') }}">Repayments</a></li>
                    <li class="breadcrumb-item active">Submit</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show"><i class="mdi mdi-alert-circle mr-2"></i>{{ session('error') }}<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="mdi mdi-alert-circle mr-2"></i>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    <form action="{{ route('client.repayments.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <!-- Eligible Repayments -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="mdi mdi-checkbox-multiple-marked mr-2"></i>Select Installments to Repay</h6>
                    </div>
                    <div class="card-body">
                        @if($eligibleRepayments->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th width="40"><input type="checkbox" id="selectAll" checked></th>
                                            <th>Loan</th>
                                            <th>Due Date</th>
                                            <th>Amount</th>
                                            <th>Penalty</th>
                                            <th>Total Due</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($eligibleRepayments as $repayment)
                                        <tr>
                                            <td><input type="checkbox" name="repayment_ids[]" value="{{ $repayment->id }}" class="repayment-checkbox" checked></td>
                                            <td>
                                                <strong>{{ $repayment->loan ? $repayment->loan->reference : '#' . $repayment->loan_id }}</strong>
                                                @if($repayment->loan && $repayment->loan->purpose)
                                                    <br><small class="text-muted">{{ $repayment->loan->purpose }}</small>
                                                @endif
                                            </td>
                                            <td>{{ optional($repayment->due_date)->format('M j, Y') ?? '-' }}</td>
                                            <td>{{ kpiMoney($repayment->amount) }}</td>
                                            <td>{{ kpiMoney($repayment->penalty ?? 0) }}</td>
                                            <td><strong>{{ kpiMoney((float)$repayment->amount + (float)($repayment->penalty ?? 0)) }}</strong></td>
                                            <td>
                                                <span class="badge badge-{{ $repayment->status === 'overdue' ? 'danger' : 'warning' }}">
                                                    {{ ucfirst($repayment->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-active">
                                            <td colspan="5" class="text-right font-weight-bold">Total Selected:</td>
                                            <td><strong id="totalAmount">N$ 0.00</strong></td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-4">
                                <i class="mdi mdi-check-circle text-success" style="font-size:48px;"></i>
                                <h5 class="mt-2 text-muted">No Pending Repayments</h5>
                                <p class="text-muted">You have no repayments due at this time.</p>
                                <a href="{{ route('client.repayments.index') }}" class="btn btn-outline-primary">Back to Repayments</a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if($eligibleRepayments->count() > 0)
        <!-- Payment Details -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="mdi mdi-credit-card mr-2"></i>Payment Method</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Payment Method <span class="text-danger">*</span></label>
                            <select name="payment_method" class="form-control" required>
                                <option value="">Select payment method</option>
                                <option value="eft">EFT / Bank Transfer</option>
                                <option value="mobile_wallet">Mobile Wallet</option>
                                <option value="cash_deposit">Cash Deposit</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Payment Reference <small class="text-muted">(optional)</small></label>
                            <input type="text" name="external_reference" class="form-control" maxlength="255" placeholder="e.g. TRX-123456789">
                            <small class="form-text text-muted">Enter the reference number from your bank or payment provider, if available.</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="mdi mdi-paperclip mr-2"></i>Proof of Payment</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Upload Proof of Payment <span class="text-danger">*</span></label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" name="proof_of_payment" id="proofOfPayment" accept=".pdf,.jpg,.jpeg,.png" required>
                                <label class="custom-file-label" for="proofOfPayment">Choose file...</label>
                            </div>
                            <small class="form-text text-muted">Accepted formats: PDF, JPG, PNG (max 5MB).</small>
                        </div>
                        <div id="filePreview" class="mt-2"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit -->
        <div class="row">
            <div class="col-12 text-right">
                <a href="{{ route('client.repayments.index') }}" class="btn btn-outline-secondary mr-2">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="mdi mdi-check mr-2"></i>Submit Repayment Request
                </button>
            </div>
        </div>
        @endif
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.repayment-checkbox');
    const totalDisplay = document.getElementById('totalAmount');

    function formatCurrency(amount) {
        return 'N$ ' + parseFloat(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function updateTotal() {
        let total = 0;
        checkboxes.forEach(cb => {
            if (cb.checked) {
                const row = cb.closest('tr');
                const amountCell = row.querySelectorAll('td')[4];
                const amountText = amountCell.textContent.replace(/[^\d.-]/g, '');
                total += parseFloat(amountText) || 0;
            }
        });
        totalDisplay.textContent = formatCurrency(total);
    }

    selectAll.addEventListener('change', function() {
        checkboxes.forEach(cb => cb.checked = this.checked);
        updateTotal();
    });

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateTotal);
    });

    updateTotal();

    // File input label update
    const fileInput = document.getElementById('proofOfPayment');
    const filePreview = document.getElementById('filePreview');
    fileInput.addEventListener('change', function() {
        const fileName = this.files[0] ? this.files[0].name : 'Choose file...';
        this.nextElementSibling.textContent = fileName;

        if (this.files[0] && this.files[0].type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                filePreview.innerHTML = '<img src="' + e.target.result + '" class="img-thumbnail" style="max-height:200px;">';
            };
            reader.readAsDataURL(this.files[0]);
        } else if (this.files[0]) {
            filePreview.innerHTML = '<i class="mdi mdi-file-pdf text-danger mr-1"></i> ' + this.files[0].name;
        } else {
            filePreview.innerHTML = '';
        }
    });
});
</script>
@endsection
