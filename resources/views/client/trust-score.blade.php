@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">Trust Score</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">Trust Score</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    @php
        $tierColors = ['bronze'=>'warning','silver'=>'secondary','gold'=>'info','platinum'=>'primary'];
        $tierColor  = $tierColors[$tier] ?? 'secondary';
        $canBorrow  = \App\Modules\TrustScore\Services\TrustScoreService::canBorrow(auth()->user());
    @endphp

    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body text-center py-4">
                    <div class="mb-3">
                        <span class="badge badge-{{ $tierColor }} px-3 py-2" style="font-size: 14px;">
                            {{ strtoupper($tier) }} TIER
                        </span>
                    </div>
                    <h1 class="display-3 font-weight-bold mb-0">{{ number_format($score, 1) }}</h1>
                    <p class="text-muted">out of 100</p>
                    <div class="progress mx-auto mb-3" style="height: 12px; max-width: 400px;">
                        <div class="progress-bar bg-{{ $tierColor }}" style="width: {{ $score }}%"></div>
                    </div>
                    <p class="mb-1">
                        <strong>Borrowing Eligibility:</strong>
                        @if($canBorrow)
                            <span class="text-success"><i class="mdi mdi-check-circle"></i> Eligible to borrow</span>
                        @else
                            <span class="text-danger"><i class="mdi mdi-close-circle"></i> Not eligible (min score: 30)</span>
                        @endif
                    </p>
                    <p class="mb-0">
                        <strong>Max Loan Amount:</strong>
                        <span class="text-primary">N$ {{ number_format($maxLoan, 2) }}</span>
                    </p>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Tier Breakdown</h5>
                    <table class="table table-bordered">
                        <thead><tr><th>Tier</th><th>Min Score</th><th>Max Loan</th><th>Status</th></tr></thead>
                        <tbody>
                            <tr class="{{ $tier === 'bronze' ? 'table-warning' : '' }}">
                                <td><span class="badge badge-warning">Bronze</span></td>
                                <td>0 – 49</td>
                                <td>N$ 5,000</td>
                                <td>{{ $tier === 'bronze' ? '<span class="badge badge-dark">Current</span>' : '' }}</td>
                            </tr>
                            <tr class="{{ $tier === 'silver' ? 'table-secondary' : '' }}">
                                <td><span class="badge badge-secondary">Silver</span></td>
                                <td>50 – 69</td>
                                <td>N$ 15,000</td>
                                <td>{!! $tier === 'silver' ? '<span class="badge badge-dark">Current</span>' : '' !!}</td>
                            </tr>
                            <tr class="{{ $tier === 'gold' ? 'table-info' : '' }}">
                                <td><span class="badge badge-info">Gold</span></td>
                                <td>70 – 84</td>
                                <td>N$ 50,000</td>
                                <td>{!! $tier === 'gold' ? '<span class="badge badge-dark">Current</span>' : '' !!}</td>
                            </tr>
                            <tr class="{{ $tier === 'platinum' ? 'table-primary' : '' }}">
                                <td><span class="badge badge-primary">Platinum</span></td>
                                <td>85 – 100</td>
                                <td>N$ 100,000</td>
                                <td>{!! $tier === 'platinum' ? '<span class="badge badge-dark">Current</span>' : '' !!}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">How to Improve Your Score</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="mdi mdi-check-circle text-success mr-2"></i> <strong>+10</strong> — Complete KYC verification</li>
                        <li class="mb-2"><i class="mdi mdi-check-circle text-success mr-2"></i> <strong>+3</strong> — Each on-time repayment</li>
                        <li class="mb-2"><i class="mdi mdi-check-circle text-success mr-2"></i> <strong>+5</strong> — Fully repay a loan</li>
                        <li class="mb-2"><i class="mdi mdi-check-circle text-success mr-2"></i> <strong>+2</strong> — Each completed referral</li>
                        <li class="mb-2"><i class="mdi mdi-close-circle text-danger mr-2"></i> <strong>-5</strong> — Late repayment</li>
                        <li class="mb-2"><i class="mdi mdi-close-circle text-danger mr-2"></i> <strong>-15</strong> — Loan default</li>
                        <li class="mb-2"><i class="mdi mdi-close-circle text-danger mr-2"></i> <strong>-3</strong> — Referred user defaults</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
