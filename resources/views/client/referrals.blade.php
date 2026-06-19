@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">Referrals</h5>
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
        <div class="col-md-5">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Your Referral Code</h5>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control font-weight-bold" id="referralCode"
                            value="{{ $referralCode }}" readonly>
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="button"
                                onclick="copyCode()">
                                <i class="mdi mdi-content-copy"></i> Copy
                            </button>
                        </div>
                    </div>
                    <p class="text-muted small">Share this code with friends. You earn <strong>+2 trust score points</strong> for each friend who completes KYC verification.</p>
                    <hr>
                    <p class="mb-1"><strong>Total Referrals:</strong> {{ $referrals->count() }}</p>
                    <p class="mb-0"><strong>Completed:</strong> {{ $referrals->where('status', 'completed')->count() }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Referred Users</h5>
                    @if($referrals->isEmpty())
                        <div class="text-center py-4">
                            <i class="mdi mdi-account-group-outline" style="font-size: 48px; color: #ccc;"></i>
                            <p class="text-muted mt-2">No referrals yet. Share your code to get started.</p>
                        </div>
                    @else
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Joined</th>
                                    <th>Status</th>
                                    <th>Points Earned</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($referrals as $referral)
                                <tr>
                                    <td>
                                        @if($referral->referred)
                                            {{ $referral->referred->first_name }} {{ $referral->referred->last_name }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $referral->created_at->format('M j, Y') }}</td>
                                    <td>
                                        @php $sc = ['pending'=>'warning','completed'=>'success','defaulted'=>'danger']; @endphp
                                        <span class="badge badge-{{ $sc[$referral->status] ?? 'secondary' }}">
                                            {{ ucfirst($referral->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($referral->status === 'completed')
                                            <span class="text-success">+2</span>
                                        @elseif($referral->status === 'defaulted')
                                            <span class="text-danger">-3</span>
                                        @else
                                            <span class="text-muted">Pending</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
function copyCode() {
    var el = document.getElementById('referralCode');
    el.select();
    document.execCommand('copy');
    alert('Referral code copied!');
}
</script>
@endpush
