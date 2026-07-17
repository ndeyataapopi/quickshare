@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0"><i class="mdi mdi-cash-plus mr-2"></i>Apply for Loan</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('client.loans.index') }}">My Loans</a></li>
                    <li class="breadcrumb-item active">Apply</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-1">Loan Application</h5>
                    
                    <!-- Loan Info Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3 col-6">
                            <div class="card text-center border-primary">
                                <div class="card-body py-2">
                                    <h6 class="text-primary mb-0">{{ config('loans.currency_symbol') }}{{ number_format($minAmount) }} - {{ number_format($maxAmount) }}</h6>
                                    <small class="text-muted">Amount Range</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="card text-center border-success">
                                <div class="card-body py-2">
                                    <h6 class="text-success mb-0">{{ $interestRate }}%</h6>
                                    <small class="text-muted">Interest Rate</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="card text-center border-warning">
                                <div class="card-body py-2">
                                    <h6 class="text-warning mb-0">{{ $platformFee }}%</h6>
                                    <small class="text-muted">Platform Fee</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="card text-center border-info">
                                <div class="card-body py-2">
                                    <h6 class="text-info mb-0">{{ $minTermDays }}-{{ $maxTermDays }} days</h6>
                                    <small class="text-muted">Term Range</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form action="{{ route('client.loans.store') }}" method="POST" id="loanForm">
                        @csrf
                        <input type="hidden" name="agreement_version" value="{{ config('loan.agreement.version') }}">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Amount <span class="text-danger">*</span></label>
                                    <div class="col-sm-9">
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">{{ config('loans.currency_symbol') }}</span>
                                            </div>
                                            <input type="number" name="amount" id="amount" step="0.01"
                                                class="form-control @error('amount') is-invalid @enderror"
                                                value="{{ old('amount') }}"
                                                min="{{ $minAmount }}" max="{{ $maxAmount }}" required>
                                        </div>
                                        @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <small class="form-text text-muted">Min: {{ config('loans.currency_symbol') }}{{ number_format($minAmount) }}, Max: {{ config('loans.currency_symbol') }}{{ number_format($maxAmount) }}</small>
                                    </div>
                                </div>
                                
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Purpose <span class="text-danger">*</span></label>
                                    <div class="col-sm-9">
                                        <select name="purpose" id="purpose" class="form-control @error('purpose') is-invalid @enderror" required>
                                            <option value="">Select purpose</option>
                                            <option value="Medical Expenses" {{ old('purpose') === 'Medical Expenses' ? 'selected' : '' }}>Medical Expenses</option>
                                            <option value="Home Repairs" {{ old('purpose') === 'Home Repairs' ? 'selected' : '' }}>Home Repairs</option>
                                            <option value="Education" {{ old('purpose') === 'Education' ? 'selected' : '' }}>Education</option>
                                            <option value="Business" {{ old('purpose') === 'Business' ? 'selected' : '' }}>Business</option>
                                            <option value="Emergency" {{ old('purpose') === 'Emergency' ? 'selected' : '' }}>Emergency</option>
                                            <option value="Personal" {{ old('purpose') === 'Personal' ? 'selected' : '' }}>Personal</option>
                                            <option value="Other" {{ old('purpose') === 'Other' ? 'selected' : '' }}>Other</option>
                                        </select>
                                        @error('purpose')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Term (days) <span class="text-danger">*</span></label>
                                    <div class="col-sm-9">
                                        <select name="repayment_period" id="repayment_period" class="form-control @error('repayment_period') is-invalid @enderror" required>
                                            <option value="">Select term</option>
                                            @foreach ($allowedDurations as $duration)
                                                <option value="{{ $duration }}" {{ old('repayment_period') == $duration ? 'selected' : '' }}>{{ $duration }} days</option>
                                            @endforeach
                                        </select>
                                        @error('repayment_period')<div class="invalid-feedback">{{ $message }}</div>@enderror>
                                    </div>
                                </div>
                                
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Description</label>
                                    <div class="col-sm-9">
                                        <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="3"
                                            placeholder="Additional details about your loan request">{{ old('description') }}</textarea>
                                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <!-- Real-time Calculation Card -->
                                <div class="card bg-light">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0"><i class="mdi mdi-calculator mr-2"></i>Loan Calculator</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <small class="text-muted">Loan Amount</small>
                                            <h5 class="text-primary mb-0" id="calcAmount">{{ config('loans.currency_symbol') }}0</h5>
                                        </div>
                                        <div class="mb-3">
                                            <small class="text-muted">Interest ({{ $interestRate }}%)</small>
                                            <h5 class="text-info mb-0" id="calcInterest">{{ config('loans.currency_symbol') }}0</h5>
                                        </div>
                                        <div class="mb-3">
                                            <small class="text-muted">Platform Fee ({{ $platformFee }}%)</small>
                                            <h5 class="text-warning mb-0" id="calcFee">{{ config('loans.currency_symbol') }}0</h5>
                                        </div>
                                        <hr>
                                        <div class="mb-3">
                                            <small class="text-muted">Total Repayment</small>
                                            <h4 class="text-success mb-0" id="calcTotal">{{ config('loans.currency_symbol') }}0</h4>
                                        </div>
                                        <div class="mb-3">
                                            <small class="text-muted">Monthly Payment</small>
                                            <h5 class="text-primary mb-0" id="calcMonthly">{{ config('loans.currency_symbol') }}0</h5>
                                        </div>
                                        <div class="alert alert-info small mb-0">
                                            <i class="mdi mdi-information mr-1"></i>
                                            Calculations are estimates. Final terms may vary.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group row mt-4">
                            <div class="col-sm-12 text-center">
                                <button type="button" class="btn btn-outline-primary btn-lg" id="viewAgreementBtn">
                                    <i class="mdi mdi-file-document-outline mr-2"></i> View Loan Agreement
                                </button>
                                <button type="submit" class="btn btn-primary btn-lg ml-2" id="submitApplicationBtn" disabled>
                                    <i class="mdi mdi-send mr-2"></i> Submit Application
                                </button>
                                <a href="{{ route('client.loans.index') }}" class="btn btn-secondary btn-lg ml-2">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="agreementModal" tabindex="-1" role="dialog" aria-labelledby="agreementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="agreementModalLabel">Loan Agreement</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="agreementLoading" class="text-center py-5">
                    <i class="mdi mdi-loading mdi-spin text-primary" style="font-size: 48px;"></i>
                    <p class="mt-2 mb-0">Loading loan agreement...</p>
                </div>
                <div id="agreementError" class="alert alert-danger d-none" role="alert"></div>
                <iframe id="agreementPdf" title="Loan Agreement Preview" class="border w-100 d-none" style="height: 65vh;"></iframe>
                <div class="mt-3 border-top pt-3">
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" name="agreement_read" value="1" form="loanForm" class="custom-control-input agreement-acceptance" id="agreementRead">
                        <label class="custom-control-label" for="agreementRead">I have read the agreement.</label>
                    </div>
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" name="agreement_terms" value="1" form="loanForm" class="custom-control-input agreement-acceptance" id="agreementTerms">
                        <label class="custom-control-label" for="agreementTerms">I agree to the terms.</label>
                    </div>
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" name="electronic_documents" value="1" form="loanForm" class="custom-control-input agreement-acceptance" id="agreementElectronicConsent">
                        <label class="custom-control-label" for="agreementElectronicConsent">I consent to electronic documents.</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const currencySymbol = '{{ config('loans.currency_symbol') }}';
    const interestRate = {{ $interestRate }};
    const platformFee = {{ $platformFee }};
    
    const amountInput = document.getElementById('amount');
    const termSelect = document.getElementById('repayment_period');
    const purposeSelect = document.getElementById('purpose');
    const viewAgreementBtn = document.getElementById('viewAgreementBtn');
    const agreementPdf = document.getElementById('agreementPdf');
    const agreementLoading = document.getElementById('agreementLoading');
    const agreementError = document.getElementById('agreementError');
    const acceptanceCheckboxes = Array.from(document.querySelectorAll('.agreement-acceptance'));
    let agreementPreviewUrl = null;
    
    // Calculator elements
    const calcAmount = document.getElementById('calcAmount');
    const calcInterest = document.getElementById('calcInterest');
    const calcFee = document.getElementById('calcFee');
    const calcTotal = document.getElementById('calcTotal');
    const calcMonthly = document.getElementById('calcMonthly');
    
    function calculateLoan() {
        const amount = parseFloat(amountInput.value) || 0;
        const termDays = parseInt(termSelect.value) || 0;
        
        if (amount > 0 && termDays > 0) {
            // Calculate interest (simple interest for demo)
            const interestAmount = (amount * interestRate / 100) * (termDays / 365);
            
            // Calculate platform fee
            const feeAmount = amount * platformFee / 100;
            
            // Calculate total repayment
            const totalRepayment = amount + interestAmount + feeAmount;
            
            // Calculate monthly payment
            const months = Math.ceil(termDays / 30);
            const monthlyPayment = totalRepayment / months;
            
            // Update display
            calcAmount.textContent = currencySymbol + amount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            calcInterest.textContent = currencySymbol + interestAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            calcFee.textContent = currencySymbol + feeAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            calcTotal.textContent = currencySymbol + totalRepayment.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            calcMonthly.textContent = currencySymbol + monthlyPayment.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        } else {
            // Reset display
            calcAmount.textContent = currencySymbol + '0';
            calcInterest.textContent = currencySymbol + '0';
            calcFee.textContent = currencySymbol + '0';
            calcTotal.textContent = currencySymbol + '0';
            calcMonthly.textContent = currencySymbol + '0';
        }
    }
    
    // Add event listeners
    amountInput.addEventListener('input', calculateLoan);
    termSelect.addEventListener('change', calculateLoan);
    
    // Form validation
    const form = document.getElementById('loanForm');
    const submitBtn = document.getElementById('submitApplicationBtn');

    function updateSubmitAvailability() {
        submitBtn.disabled = !acceptanceCheckboxes.every(checkbox => checkbox.checked);
    }

    function resetAgreementAcceptance() {
        acceptanceCheckboxes.forEach(checkbox => checkbox.checked = false);
        updateSubmitAvailability();

        if (agreementPreviewUrl) {
            URL.revokeObjectURL(agreementPreviewUrl);
            agreementPreviewUrl = null;
        }

        agreementPdf.src = 'about:blank';
        agreementPdf.classList.add('d-none');
    }

    acceptanceCheckboxes.forEach(checkbox => checkbox.addEventListener('change', updateSubmitAvailability));
    amountInput.addEventListener('input', resetAgreementAcceptance);
    termSelect.addEventListener('change', resetAgreementAcceptance);

    viewAgreementBtn.addEventListener('click', async function() {
        if (!amountInput.checkValidity()) {
            amountInput.reportValidity();
            return;
        }

        if (!termSelect.checkValidity()) {
            termSelect.reportValidity();
            return;
        }

        agreementLoading.classList.remove('d-none');
        agreementError.classList.add('d-none');
        agreementPdf.classList.add('d-none');
        $('#agreementModal').modal('show');

        const previewUrl = new URL('{{ route('client.loans.agreement-preview') }}', window.location.origin);
        previewUrl.searchParams.set('amount', amountInput.value);
        previewUrl.searchParams.set('repayment_period', termSelect.value);

        try {
            const response = await fetch(previewUrl.toString(), {
                headers: { 'Accept': 'application/pdf' }
            });

            if (!response.ok || !response.headers.get('content-type')?.includes('application/pdf')) {
                throw new Error('Unable to load the loan agreement preview.');
            }

            if (agreementPreviewUrl) {
                URL.revokeObjectURL(agreementPreviewUrl);
            }

            agreementPreviewUrl = URL.createObjectURL(await response.blob());
            agreementPdf.src = agreementPreviewUrl;
            agreementPdf.classList.remove('d-none');
        } catch (error) {
            agreementError.textContent = error.message;
            agreementError.classList.remove('d-none');
        } finally {
            agreementLoading.classList.add('d-none');
        }
    });
    
    form.addEventListener('submit', function(e) {
        const amount = parseFloat(amountInput.value);
        const term = parseInt(termSelect.value);
        const purpose = purposeSelect.value;
        
        // Custom validation
        if (amount < {{ $minAmount }} || amount > {{ $maxAmount }}) {
            e.preventDefault();
            alert('Amount must be between {{ config('loans.currency_symbol') }}{{ number_format($minAmount) }} and {{ config('loans.currency_symbol') }}{{ number_format($maxAmount) }}');
            return;
        }
        
        if (!purpose) {
            e.preventDefault();
            alert('Please select a loan purpose');
            return;
        }
        
        if (!term || term < {{ $minTermDays }} || term > {{ $maxTermDays }}) {
            e.preventDefault();
            alert('Please select a valid repayment term');
            return;
        }
        
        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="mdi mdi-loading mdi-spin mr-2"></i> Processing...';
    });
    
    // Add "Other" purpose field
    purposeSelect.addEventListener('change', function() {
        if (this.value === 'Other') {
            const otherPurposeInput = document.createElement('input');
            otherPurposeInput.type = 'text';
            otherPurposeInput.name = 'purpose_other';
            otherPurposeInput.className = 'form-control mt-2';
            otherPurposeInput.placeholder = 'Please specify the purpose';
            otherPurposeInput.required = true;
            
            const existingOther = this.parentElement.querySelector('input[name="purpose_other"]');
            if (existingOther) {
                existingOther.remove();
            }
            
            this.parentElement.appendChild(otherPurposeInput);
        } else {
            const otherInput = this.parentElement.querySelector('input[name="purpose_other"]');
            if (otherInput) {
                otherInput.remove();
            }
        }
    });
    
    // Initialize calculation
    calculateLoan();
    
    // Auto-populate with previous values if available
    if (amountInput.value) {
        calculateLoan();
    }
});
</script>
@endsection
