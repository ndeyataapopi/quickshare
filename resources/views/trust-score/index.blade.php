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
        $score = (float) (auth()->user()->trust_score ?? 0);
        $tier = auth()->user()->trust_tier ?? 'bronze';
        $tierColors = ['bronze'=>'warning','silver'=>'secondary','gold'=>'warning','platinum'=>'primary'];
        $tierColor = $tierColors[$tier] ?? 'info';
        $pct = min(100, $score);
    @endphp
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title text-uppercase mb-3">Your Trust Score</h5>
                    <div class="display-1 font-weight-bold text-{{ $tierColor }} mb-2">{{ number_format($score) }}</div>
                    <span class="badge badge-{{ $tierColor }} badge-pill" style="font-size:14px;">{{ ucfirst($tier) }} Tier</span>
                    <div class="progress mt-3" style="height:10px;">
                        <div class="progress-bar bg-{{ $tierColor }}" style="width:{{ $pct }}%"></div>
                    </div>
                    <small class="text-muted mt-2 d-block">Score out of 100</small>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">How to Improve Your Score</h5>
                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex align-items-center">
                            <i class="mdi mdi-check-circle text-success mr-3" style="font-size:24px;"></i>
                            <div>
                                <strong>Make timely repayments</strong>
                                <p class="mb-0 text-muted small">Paying on time boosts your trust score significantly.</p>
                            </div>
                        </div>
                        <div class="list-group-item d-flex align-items-center">
                            <i class="mdi mdi-check-circle text-success mr-3" style="font-size:24px;"></i>
                            <div>
                                <strong>Complete KYC verification</strong>
                                <p class="mb-0 text-muted small">Verified identity increases platform confidence.</p>
                            </div>
                        </div>
                        <div class="list-group-item d-flex align-items-center">
                            <i class="mdi mdi-check-circle text-success mr-3" style="font-size:24px;"></i>
                            <div>
                                <strong>Refer new users</strong>
                                <p class="mb-0 text-muted small">Active referrals earn you bonus points.</p>
                            </div>
                        </div>
                        <div class="list-group-item d-flex align-items-center">
                            <i class="mdi mdi-check-circle text-success mr-3" style="font-size:24px;"></i>
                            <div>
                                <strong>Regular platform activity</strong>
                                <p class="mb-0 text-muted small">Consistent use of the platform keeps your score active.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @if(isset($history) && $history->count() > 0)
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Score History</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="thead-light">
                                <tr><th>Event</th><th>Points</th><th>Date</th></tr>
                            </thead>
                            <tbody>
                                @foreach($history as $h)
                                <tr>
                                    <td>{{ $h->event ?? '-' }}</td>
                                    <td class="{{ ($h->points ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">{{ ($h->points ?? 0) >= 0 ? '+' : '' }}{{ $h->points ?? 0 }}</td>
                                    <td>{{ $h->created_at->format('M j, Y') }}</td>
                                </tr>
                                @endforeach
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
