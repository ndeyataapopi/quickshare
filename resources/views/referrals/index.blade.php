@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">My Referrals</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">Referrals</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Your Referral Code</h5>
                    <p class="text-muted mb-3">Share this code with friends. Earn rewards when they sign up and transact.</p>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control font-weight-bold" value="{{ auth()->user()->referral_code ?? 'N/A' }}" id="referralCode" readonly>
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="button" onclick="copyCode()"><i class="mdi mdi-content-copy"></i> Copy</button>
                        </div>
                    </div>
                    <div class="d-flex">
                        <div class="mr-4 text-center">
                            <h4 class="mb-0 text-primary">{{ $stats['total_referrals'] ?? 0 }}</h4>
                            <small class="text-muted">Total Referrals</small>
                        </div>
                        <div class="mr-4 text-center">
                            <h4 class="mb-0 text-success">{{ $stats['active_referrals'] ?? 0 }}</h4>
                            <small class="text-muted">Active</small>
                        </div>
                        <div class="text-center">
                            <h4 class="mb-0 text-warning">N\$ {{ number_format($stats['total_rewards'] ?? 0) }}</h4>
                            <small class="text-muted">Total Rewards</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Referral History</h5>
                    @if(isset($referrals) && $referrals->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="thead-light">
                                <tr><th>Name</th><th>Status</th><th>Reward</th><th>Date</th></tr>
                            </thead>
                            <tbody>
                                @foreach($referrals as $ref)
                                <tr>
                                    <td>{{ $ref->referred->first_name ?? '-' }} {{ $ref->referred->last_name ?? '' }}</td>
                                    <td><span class="badge badge-{{ $ref->status === 'active' ? 'success' : 'warning' }}">{{ ucfirst($ref->status) }}</span></td>
                                    <td>N\$ {{ number_format($ref->reward_amount ?? 0) }}</td>
                                    <td>{{ $ref->created_at->format('M j, Y') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="text-center py-4">
                        <i class="mdi mdi-account-group text-muted" style="font-size:48px;"></i>
                        <p class="text-muted mt-2">No referrals yet. Share your code!</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function copyCode() {
    var el = document.getElementById('referralCode');
    el.select();
    document.execCommand('copy');
    alert('Referral code copied!');
}
</script>
@endsection
