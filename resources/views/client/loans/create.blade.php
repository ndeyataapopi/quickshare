@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">Apply for Loan</h5>
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
                    <p class="text-muted mb-4">
                        Loan amounts: <strong>{{ config('loans.currency_symbol') }}{{ number_format($minAmount) }} – {{ config('loans.currency_symbol') }}{{ number_format($maxAmount) }}</strong> &nbsp;|&nbsp;
                        Term: <strong>{{ $minTermDays }}–{{ $maxTermDays }} days</strong> &nbsp;|&nbsp;
                        Interest: <strong>{{ $interestRate }}%</strong> &nbsp;|&nbsp;
                        Platform fee: <strong>{{ $platformFee }}%</strong>
                    </p>
                    <form action="{{ route('client.loans.store') }}" method="POST">
                        @csrf
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Amount ({{ config('loans.currency_symbol') }}) <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="number" name="amount" step="0.01"
                                    class="form-control @error('amount') is-invalid @enderror"
                                    value="{{ old('amount') }}"
                                    min="{{ $minAmount }}" max="{{ $maxAmount }}" required>
                                @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <small class="form-text text-muted">Min: {{ config('loans.currency_symbol') }}{{ number_format($minAmount) }}, Max: {{ config('loans.currency_symbol') }}{{ number_format($maxAmount) }}</small>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Purpose <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="text" name="purpose"
                                    class="form-control @error('purpose') is-invalid @enderror"
                                    value="{{ old('purpose') }}"
                                    placeholder="e.g. Medical expenses, Home repairs" required>
                                @error('purpose')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-sm-3 col-form-label">Repayment Term (days) <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <select name="repayment_period" class="form-control @error('repayment_period') is-invalid @enderror" required>
                                    <option value="">Select term</option>
                                    @for ($d = $minTermDays; $d <= $maxTermDays; $d += 7)
                                        <option value="{{ $d }}" {{ old('repayment_period') == $d ? 'selected' : '' }}>{{ $d }} days</option>
                                    @endfor
                                </select>
                                @error('repayment_period')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <small class="form-text text-muted">Between {{ $minTermDays }} and {{ $maxTermDays }} days</small>
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
                        <div class="form-group row">
                            <div class="col-sm-9 offset-sm-3">
                                <button type="submit" class="btn btn-primary mr-2">
                                    <i class="mdi mdi-send mr-1"></i> Submit Application
                                </button>
                                <a href="{{ route('client.loans.index') }}" class="btn btn-secondary">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
