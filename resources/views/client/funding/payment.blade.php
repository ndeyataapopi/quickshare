@php
$loan = $transaction->loan;
$target = (float) ($loan->approved_amount ?? $loan->requested_amount);
$funded = (float) $loan->funded_amount;
$remaining = max(0, $target - $funded);
$progress = $target > 0 ? round(($funded / $target) * 100, 2) : 0;
$myAmount = (float) $transaction->amount;
$interest = $myAmount * ($transaction->interest_rate / 100) * ($loan->loan_term_days / 365);
$expectedProfit = round($interest, 2);
$expectedReturn = round($myAmount + $expectedProfit, 2);
$currency = config('loans.currency_symbol', 'N$');
$banks = config('payments.banks', []);
$wallets = config('payments.wallets', []);
$cashDepositBank = config('payments.cash_deposit.default_bank');
@endphp

@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">Complete Payment</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('client.investments.index') }}">Investments</a></li>
                    <li class="breadcrumb-item active">Complete Payment</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <small class="text-muted d-block">Requested Amount</small>
                            <h4 class="text-primary mb-0">{{ $currency }} {{ number_format($target, 2) }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <small class="text-muted d-block">Amount Already Funded</small>
                            <h4 class="text-info mb-0">{{ $currency }} {{ number_format($funded, 2) }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <small class="text-muted d-block">Amount Remaining</small>
                            <h4 class="text-warning mb-0">{{ $currency }} {{ number_format($remaining, 2) }}</h4>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="card border-primary h-100">
                        <div class="card-body">
                            <h5 class="card-title text-uppercase mb-3">Your Investment</h5>
                            <div class="row mb-2"><div class="col-6 text-muted">My Funding Amount</div><div class="col-6 font-weight-bold">{{ $currency }} {{ number_format($myAmount, 2) }}</div></div>
                            <div class="row mb-2"><div class="col-6 text-muted">Interest Rate</div><div class="col-6">{{ $transaction->interest_rate }}% p.a.</div></div>
                            <div class="row mb-2"><div class="col-6 text-muted">Expected Return</div><div class="col-6 text-success font-weight-bold">{{ $currency }} {{ number_format($expectedReturn, 2) }}</div></div>
                            <div class="row mb-2"><div class="col-6 text-muted">Expected Profit</div><div class="col-6 text-success">{{ $currency }} {{ number_format($expectedProfit, 2) }}</div></div>
                            <div class="row mb-2"><div class="col-6 text-muted">Total Expected Repayment</div><div class="col-6 font-weight-bold">{{ $currency }} {{ number_format($expectedReturn, 2) }}</div></div>
                            <div class="row"><div class="col-6 text-muted">Funding Percentage</div><div class="col-6">{{ $target > 0 ? round(($myAmount / $target) * 100, 2) : 0 }}%</div></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title text-uppercase mb-3">Overall Funding Progress</h5>
                            <div class="progress mb-2" style="height: 24px;">
                                <div class="progress-bar bg-{{ $progress >= 75 ? 'success' : ($progress >= 50 ? 'warning' : 'info') }}" style="width: {{ min(100, $progress) }}%;">{{ $progress }}%</div>
                            </div>
                            <p class="text-muted small">{{ $currency }} {{ number_format($funded, 2) }} of {{ $currency }} {{ number_format($target, 2) }} raised.</p>
                            <div class="bg-light p-2 rounded mt-3">
                                <small class="text-muted d-block">QuickShare Payment Reference</small>
                                <div class="input-group">
                                    <input type="text" id="copyReference" class="form-control form-control-sm" value="{{ $transaction->payment_reference }}" readonly>
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="copyToClipboard('copyReference')">
                                            <i class="mdi mdi-content-copy mr-1"></i> Copy
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Payment Method</h5>
                    <form method="POST" action="{{ route('client.funding.payment.submit', $transaction) }}" enctype="multipart/form-data" id="paymentForm">
                        @csrf
                        <input type="hidden" name="payment_reference" value="{{ $transaction->payment_reference }}">

                        <div class="form-group">
                            <label>Select how you will pay <span class="text-danger">*</span></label>
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <label class="card p-3 h-100 cursor-pointer payment-method-card @if(old('payment_method') === 'eft') border-primary @endif" id="method-eft-label">
                                        <input type="radio" name="payment_method" value="eft" class="d-none" {{ old('payment_method') === 'eft' ? 'checked' : '' }} required>
                                        <div class="text-center">
                                            <i class="mdi mdi-bank" style="font-size: 32px;"></i>
                                            <h6 class="mt-2 mb-0">EFT / Instant Bank Transfer</h6>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="card p-3 h-100 cursor-pointer payment-method-card @if(old('payment_method') === 'mobile_wallet') border-primary @endif" id="method-mobile-label">
                                        <input type="radio" name="payment_method" value="mobile_wallet" class="d-none" {{ old('payment_method') === 'mobile_wallet' ? 'checked' : '' }} required>
                                        <div class="text-center">
                                            <i class="mdi mdi-cellphone" style="font-size: 32px;"></i>
                                            <h6 class="mt-2 mb-0">Mobile Wallet</h6>
                                        </div>
                                    </label>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="card p-3 h-100 cursor-pointer payment-method-card @if(old('payment_method') === 'cash_deposit') border-primary @endif" id="method-cash-label">
                                        <input type="radio" name="payment_method" value="cash_deposit" class="d-none" {{ old('payment_method') === 'cash_deposit' ? 'checked' : '' }} required>
                                        <div class="text-center">
                                            <i class="mdi mdi-cash" style="font-size: 32px;"></i>
                                            <h6 class="mt-2 mb-0">Cash Deposit</h6>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            @error('payment_method')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <!-- EFT Options -->
                        <div id="eft-options" class="payment-options d-none">
                            <div class="form-group">
                                <label>Choose Bank <span class="text-danger">*</span></label>
                                <select name="payment_method_detail" class="form-control @error('payment_method_detail') is-invalid @enderror bank-select" data-type="bank">
                                    <option value="">Select bank</option>
                                    @foreach($banks as $key => $bank)
                                        <option value="{{ $key }}" data-name="{{ $bank['name'] }}" data-account="{{ $bank['account_number'] }}" data-account-name="{{ $bank['account_name'] }}" data-branch="{{ $bank['branch_name'] }}" data-branch-code="{{ $bank['branch_code'] }}" {{ old('payment_method_detail') === $key ? 'selected' : '' }}>{{ $bank['name'] }}</option>
                                    @endforeach
                                </select>
                                @error('payment_method_detail')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div id="eft-details" class="d-none">
                                <div class="alert alert-info">
                                    <h6 class="font-weight-bold">EFT Details</h6>
                                    <div class="row small">
                                        <div class="col-md-6"><strong>Bank:</strong> <span id="eft-bank-name"></span></div>
                                        <div class="col-md-6"><strong>Account Name:</strong> <span id="eft-account-name"></span></div>
                                        <div class="col-md-6"><strong>Account Number:</strong> <span id="eft-account-number"></span></div>
                                        <div class="col-md-6"><strong>Branch:</strong> <span id="eft-branch-name"></span></div>
                                        <div class="col-md-6"><strong>Branch Code:</strong> <span id="eft-branch-code"></span></div>
                                        <div class="col-md-6"><strong>Reference:</strong> {{ $transaction->payment_reference }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Mobile Wallet Options -->
                        <div id="mobile-options" class="payment-options d-none">
                            <div class="form-group">
                                <label>Choose Wallet <span class="text-danger">*</span></label>
                                <select name="payment_method_detail" class="form-control @error('payment_method_detail') is-invalid @enderror wallet-select" data-type="wallet" disabled>
                                    <option value="">Select wallet</option>
                                    @foreach($wallets as $key => $wallet)
                                        <option value="{{ $key }}" data-name="{{ $wallet['name'] }}" data-cellphone="{{ $wallet['cellphone'] }}" data-instructions="{{ $wallet['instructions'] }}" {{ old('payment_method_detail') === $key ? 'selected' : '' }}>{{ $wallet['name'] }}</option>
                                    @endforeach
                                </select>
                                @error('payment_method_detail')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div id="mobile-details" class="d-none">
                                <div class="alert alert-info">
                                    <h6 class="font-weight-bold">Mobile Wallet Details</h6>
                                    <p class="mb-1"><strong>Wallet:</strong> <span id="mobile-wallet-name"></span></p>
                                    <p class="mb-1"><strong>Cellphone Number:</strong> <span id="mobile-cellphone"></span></p>
                                    <p class="mb-0"><strong>Reference:</strong> {{ $transaction->payment_reference }}</p>
                                    <hr>
                                    <p class="small mb-0" id="mobile-instructions"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Cash Deposit Options -->
                        <div id="cash-options" class="payment-options d-none">
                            <div class="form-group">
                                <label>Selected Bank <span class="text-danger">*</span></label>
                                <select name="payment_method_detail" class="form-control @error('payment_method_detail') is-invalid @enderror bank-select" data-type="cash">
                                    <option value="">Select bank</option>
                                    @foreach($banks as $key => $bank)
                                        <option value="{{ $key }}" data-name="{{ $bank['name'] }}" data-account="{{ $bank['account_number'] }}" data-account-name="{{ $bank['account_name'] }}" data-branch="{{ $bank['branch_name'] }}" data-branch-code="{{ $bank['branch_code'] }}" {{ old('payment_method_detail', $cashDepositBank) === $key ? 'selected' : '' }}>{{ $bank['name'] }}</option>
                                    @endforeach
                                </select>
                                @error('payment_method_detail')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div id="cash-details" class="d-none">
                                <div class="alert alert-info">
                                    <h6 class="font-weight-bold">Cash Deposit Instructions</h6>
                                    <p class="small mb-2">{{ config('payments.cash_deposit.instructions', 'Deposit cash into the selected bank account at any branch. Write the reference on the deposit slip.') }}</p>
                                    <div class="row small">
                                        <div class="col-md-6"><strong>Bank:</strong> <span id="cash-bank-name"></span></div>
                                        <div class="col-md-6"><strong>Branch:</strong> <span id="cash-branch-name"></span></div>
                                        <div class="col-md-6"><strong>Account Number:</strong> <span id="cash-account-number"></span></div>
                                        <div class="col-md-6"><strong>Reference:</strong> {{ $transaction->payment_reference }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Reference Number <span class="text-danger">*</span></label>
                                    <input type="text" name="reference_number" class="form-control @error('reference_number') is-invalid @enderror" value="{{ old('reference_number') }}" placeholder="Your bank / wallet reference">
                                    @error('reference_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Transaction Number</label>
                                    <input type="text" name="transaction_number" class="form-control @error('transaction_number') is-invalid @enderror" value="{{ old('transaction_number') }}" placeholder="Transaction / receipt number">
                                    @error('transaction_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Payment Date <span class="text-danger">*</span></label>
                                    <input type="date" name="payment_date" class="form-control @error('payment_date') is-invalid @enderror" value="{{ old('payment_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                                    @error('payment_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Proof of Payment (PDF, JPG, PNG, max 5MB) <span class="text-danger">*</span></label>
                                    <input type="file" name="proof_of_payment" class="form-control-file @error('proof_of_payment') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png" required>
                                    @error('proof_of_payment')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Notes (optional)</label>
                            <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="2" placeholder="Any extra details...">{{ old('notes') }}</textarea>
                            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-group form-check">
                            <input type="checkbox" name="payment_confirmation" value="1" class="form-check-input @error('payment_confirmation') is-invalid @enderror" id="paymentConfirmation" {{ old('payment_confirmation') ? 'checked' : '' }} required>
                            <label class="form-check-label" for="paymentConfirmation">I confirm I have made this payment.</label>
                            @error('payment_confirmation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <button type="submit" class="btn btn-success">
                            <i class="mdi mdi-check mr-1"></i> Submit Payment
                        </button>
                        <a href="{{ route('client.funding.show', $transaction) }}" class="btn btn-secondary ml-2">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyToClipboard(elementId) {
    const el = document.getElementById(elementId);
    el.select();
    el.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(el.value).then(() => {
        alert('Reference copied to clipboard');
    });
}

const paymentForm = document.getElementById('paymentForm');
const methodRadios = paymentForm.querySelectorAll('input[name="payment_method"]');
const optionDivs = {
    eft: document.getElementById('eft-options'),
    mobile_wallet: document.getElementById('mobile-options'),
    cash_deposit: document.getElementById('cash-options'),
};
const methodLabels = {
    eft: document.getElementById('method-eft-label'),
    mobile_wallet: document.getElementById('method-mobile-label'),
    cash_deposit: document.getElementById('method-cash-label'),
};

function showOptions(method) {
    Object.keys(optionDivs).forEach(key => {
        optionDivs[key].classList.toggle('d-none', key !== method);
        const select = optionDivs[key].querySelector('select');
        if (select) select.disabled = key !== method;
    });
    Object.values(methodLabels).forEach(label => label.classList.remove('border-primary'));
    if (methodLabels[method]) methodLabels[method].classList.add('border-primary');
}

methodRadios.forEach(radio => {
    radio.addEventListener('change', () => showOptions(radio.value));
});

// Show details when bank/wallet selected
function bindSelect(select, detailsId, fillers) {
    const details = document.getElementById(detailsId);
    select.addEventListener('change', function () {
        const option = this.options[this.selectedIndex];
        if (!this.value) {
            details.classList.add('d-none');
            return;
        }
        Object.keys(fillers).forEach(key => {
            const el = document.getElementById(fillers[key]);
            if (el) el.textContent = option.dataset[key] || '-';
        });
        details.classList.remove('d-none');
    });
    if (select.value) select.dispatchEvent(new Event('change'));
}

bindSelect(document.querySelector('.bank-select[data-type="bank"]'), 'eft-details', {
    name: 'eft-bank-name',
    accountName: 'eft-account-name',
    account: 'eft-account-number',
    branch: 'eft-branch-name',
    branchCode: 'eft-branch-code',
});

bindSelect(document.querySelector('.wallet-select[data-type="wallet"]'), 'mobile-details', {
    name: 'mobile-wallet-name',
    cellphone: 'mobile-cellphone',
    instructions: 'mobile-instructions',
});

bindSelect(document.querySelector('.bank-select[data-type="cash"]'), 'cash-details', {
    name: 'cash-bank-name',
    account: 'cash-account-number',
    branch: 'cash-branch-name',
});

const checkedMethod = paymentForm.querySelector('input[name="payment_method"]:checked');
if (checkedMethod) showOptions(checkedMethod.value);
</script>
@endsection
