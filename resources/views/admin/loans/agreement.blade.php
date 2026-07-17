@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">Loan Agreement</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.loans.index') }}">Loans</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.loans.show', $loan) }}">{{ $loan->reference }}</a></li>
                    <li class="breadcrumb-item active">Agreement</li>
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
                    <h5 class="card-title text-uppercase mb-4">Loan Summary</h5>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Loan Number</div><div class="col-sm-8 font-weight-bold">{{ $loan->reference }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Borrower</div><div class="col-sm-8">{{ $loan->borrower->first_name }} {{ $loan->borrower->last_name }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Borrower Email</div><div class="col-sm-8">{{ $loan->borrower->email }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Trust Score</div><div class="col-sm-8">{{ number_format($trustScore, 2) }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Trust Tier</div><div class="col-sm-8 font-weight-bold">{{ ucfirst($tier) }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Requested Amount</div><div class="col-sm-8">{{ config('loans.currency_symbol') }} {{ number_format($loan->requested_amount, 2) }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Total Repayment</div><div class="col-sm-8 font-weight-bold">{{ config('loans.currency_symbol') }} {{ number_format($loan->total_repayment, 2) }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Repayment Date</div><div class="col-sm-8">{{ $loan->repayment_date ? $loan->repayment_date->format('d F Y') : '-' }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Term</div><div class="col-sm-8">{{ $loan->loan_term_days }} days</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Agreement Version</div><div class="col-sm-8">{{ $loan->agreement_version }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Generated At</div><div class="col-sm-8">{{ $loan->agreement_generated_at ? $loan->agreement_generated_at->format('M j, Y g:i A') : '-' }}</div></div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-4">Configuration Snapshot</h5>
                    @php $snapshot = $loan->configuration_snapshot ?? []; @endphp
                    @if (! empty($snapshot))
                        <dl class="row mb-0">
                            @foreach ($snapshot as $key => $value)
                                <dt class="col-sm-4 text-muted">{{ ucwords(str_replace('_', ' ', $key)) }}</dt>
                                <dd class="col-sm-8">
                                    @if (is_array($value))
                                        <pre class="mb-0 p-2 bg-light border rounded"><code>{{ json_encode($value, JSON_PRETTY_PRINT) }}</code></pre>
                                    @else
                                        {{ $value }}
                                    @endif
                                </dd>
                            @endforeach
                        </dl>
                    @else
                        <p class="text-muted mb-0">No configuration snapshot recorded.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Actions</h5>
                    @if(session('success'))
                        <div class="alert alert-success p-2">{{ session('success') }}</div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger p-2">{{ session('error') }}</div>
                    @endif

                    @if($loan->agreement_path)
                        <a href="{{ route('admin.loans.agreement.download', $loan) }}" class="btn btn-primary btn-block mb-2">
                            <i class="mdi mdi-download mr-1"></i> Download Agreement
                        </a>
                        <a href="{{ route('admin.loans.agreement.pdf', $loan) }}" target="_blank" class="btn btn-secondary btn-block mb-2">
                            <i class="mdi mdi-printer mr-1"></i> Print Agreement
                        </a>
                    @else
                        <button class="btn btn-primary btn-block mb-2" disabled><i class="mdi mdi-download mr-1"></i> Download Agreement</button>
                        <button class="btn btn-secondary btn-block mb-2" disabled><i class="mdi mdi-printer mr-1"></i> Print Agreement</button>
                    @endif

                    @if($loan->agreement_path)
                        <form method="POST" action="{{ route('admin.loans.agreement.resend', $loan) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-info btn-block mb-2" onclick="return confirm('Resend agreement email to {{ $loan->borrower->email }}?')">
                                <i class="mdi mdi-email-send mr-1"></i> Resend Email
                            </button>
                        </form>
                    @else
                        <button class="btn btn-outline-info btn-block mb-2" disabled><i class="mdi mdi-email-send mr-1"></i> Resend Email</button>
                    @endif

                    <a href="{{ route('admin.loans.show', $loan) }}" class="btn btn-outline-secondary btn-block">
                        <i class="mdi mdi-arrow-left mr-1"></i> Back to Loan
                    </a>
                </div>
            </div>
        </div>
    </div>

    @if($loan->agreement_path)
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body p-0">
                        <iframe src="{{ route('admin.loans.agreement.pdf', $loan) }}" title="Loan Agreement" class="w-100 border-0" style="height: 75vh;"></iframe>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
