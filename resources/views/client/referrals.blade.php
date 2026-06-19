@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0"><i class="mdi mdi-account-group mr-2"></i>Referrals</h5>
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
    @php
        $totalReferrals = $referrals->count();
        $completedReferrals = $referrals->where('status', 'completed')->count();
        $pendingReferrals = $referrals->where('status', 'pending')->count();
        $pointsEarned = $completedReferrals * 2;
        $pointsLost = $referrals->where('status', 'defaulted')->count() * 3;
        $netPoints = $pointsEarned - $pointsLost;
    @endphp

    <!-- Referral Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center border-primary">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-primary mb-0">{{ $totalReferrals }}</h4>
                    <small class="text-muted">Total Referrals</small>
                    <div class="mt-2">
                        <small class="text-info">{{ $completedReferrals }} completed</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-success">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-success mb-0">+{{ $pointsEarned }}</h4>
                    <small class="text-muted">Points Earned</small>
                    <div class="mt-2">
                        <small class="text-success"><i class="mdi mdi-trending-up"></i> +2 per referral</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-warning">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-warning mb-0">{{ $pendingReferrals }}</h4>
                    <small class="text-muted">Pending</small>
                    <div class="mt-2">
                        <small class="text-muted">Awaiting KYC</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-{{ $netPoints >= 0 ? 'info' : 'danger' }}">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-{{ $netPoints >= 0 ? 'info' : 'danger' }} mb-0">{{ $netPoints >= 0 ? '+' : '' }}{{ $netPoints }}</h4>
                    <small class="text-muted">Net Points</small>
                    <div class="mt-2">
                        <small class="{{ $pointsLost > 0 ? 'text-danger' : 'text-muted' }}">{{ $pointsLost > 0 ? '-' . $pointsLost . ' lost' : 'No losses' }}</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-5">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Your Referral Code</h5>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control font-weight-bold text-center" id="referralCode"
                            value="{{ $referralCode }}" readonly style="font-size: 18px; letter-spacing: 2px;">
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="button" id="copyBtn">
                                <i class="mdi mdi-content-copy"></i> Copy
                            </button>
                        </div>
                    </div>
                    
                    <!-- Share Options -->
                    <div class="mb-3">
                        <label class="form-label text-muted small">Share via:</label>
                        <div class="btn-group w-100">
                            <button class="btn btn-outline-success btn-sm" id="shareWhatsApp">
                                <i class="mdi mdi-whatsapp"></i> WhatsApp
                            </button>
                            <button class="btn btn-outline-info btn-sm" id="shareEmail">
                                <i class="mdi mdi-email"></i> Email
                            </button>
                            <button class="btn btn-outline-primary btn-sm" id="shareFacebook">
                                <i class="mdi mdi-facebook"></i> Facebook
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" id="shareTwitter">
                                <i class="mdi mdi-twitter"></i> Twitter
                            </button>
                        </div>
                    </div>
                    
                    <!-- Referral Link -->
                    <div class="mb-3">
                        <label class="form-label text-muted small">Referral Link:</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" id="referralLink"
                                value="{{ url('/register?ref=' . $referralCode) }}" readonly>
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary" type="button" id="copyLinkBtn">
                                    <i class="mdi mdi-content-copy"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info small">
                        <i class="mdi mdi-information mr-2"></i>
                        <strong>How it works:</strong> Share your code with friends. You earn <strong>+2 trust score points</strong> for each friend who completes KYC verification. If a referred user defaults, you lose <strong>-3 points</strong>.
                    </div>
                </div>
            </div>
            
            <!-- Referral Progress -->
            <div class="card mt-3">
                <div class="card-body">
                    <h6 class="card-title text-uppercase mb-3">Referral Progress</h6>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between mb-1">
                            <small class="text-muted">Next Milestone: 10 Referrals</small>
                            <small class="font-weight-bold">{{ $totalReferrals }}/10</small>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-primary" style="width: {{ min(100, ($totalReferrals / 10) * 100) }}%"></div>
                        </div>
                    </div>
                    <small class="text-muted">Complete 10 referrals to earn a bonus <strong>+5 trust score points</strong>!</small>
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title text-uppercase mb-0">Referred Users</h5>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary active" data-filter="all">All</button>
                            <button class="btn btn-outline-success" data-filter="completed">Completed</button>
                            <button class="btn btn-outline-warning" data-filter="pending">Pending</button>
                            <button class="btn btn-outline-danger" data-filter="defaulted">Defaulted</button>
                        </div>
                    </div>
                    @if($referrals->isEmpty())
                        <div class="text-center py-5">
                            <i class="mdi mdi-account-group-outline text-muted" style="font-size:64px;"></i>
                            <h6 class="mt-3 text-muted">No Referrals Yet</h6>
                            <p class="text-muted">Share your referral code to start earning trust score points!</p>
                            <button class="btn btn-primary" id="startReferring">
                                <i class="mdi mdi-share mr-1"></i> Start Referring
                            </button>
                        </div>
                    @else
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Joined</th>
                                    <th>Status</th>
                                    <th>Points</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($referrals as $referral)
                                <tr data-status="{{ $referral->status }}">
                                    <td>
                                        @if($referral->referred)
                                            <div>
                                                <strong>{{ $referral->referred->first_name }} {{ $referral->referred->last_name }}</strong>
                                                <br><small class="text-muted">{{ $referral->referred->email }}</small>
                                            </div>
                                        @else
                                            <span class="text-muted">Pending registration</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $referral->created_at->format('M j, Y') }}
                                        <br><small class="text-muted">{{ $referral->created_at->diffForHumans() }}</small>
                                    </td>
                                    <td>
                                        @php 
                                            $statusColors = [
                                                'pending' => 'warning',
                                                'completed' => 'success', 
                                                'defaulted' => 'danger'
                                            ]; 
                                            $statusIcons = [
                                                'pending' => 'clock',
                                                'completed' => 'check-circle', 
                                                'defaulted' => 'close-circle'
                                            ]; 
                                        @endphp
                                        <span class="badge badge-{{ $statusColors[$referral->status] ?? 'secondary' }}">
                                            <i class="mdi mdi-{{ $statusIcons[$referral->status] ?? 'help-circle' }} mr-1"></i>
                                            {{ ucfirst($referral->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($referral->status === 'completed')
                                            <span class="text-success font-weight-bold">+2</span>
                                        @elseif($referral->status === 'defaulted')
                                            <span class="text-danger font-weight-bold">-3</span>
                                        @else
                                            <span class="text-muted">Pending</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($referral->referred)
                                            <button class="btn btn-sm btn-outline-primary remind-btn" data-email="{{ $referral->referred->email }}">
                                                <i class="mdi mdi-bell"></i>
                                            </button>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const referralCode = document.getElementById('referralCode').value;
    const referralLink = document.getElementById('referralLink').value;
    
    // Copy referral code
    document.getElementById('copyBtn').addEventListener('click', function() {
        copyToClipboard(referralCode);
        showToast('Referral code copied to clipboard!');
    });
    
    // Copy referral link
    document.getElementById('copyLinkBtn').addEventListener('click', function() {
        copyToClipboard(referralLink);
        showToast('Referral link copied to clipboard!');
    });
    
    // Share functionality
    document.getElementById('shareWhatsApp').addEventListener('click', function() {
        const message = `Join QuickShare using my referral code: ${referralCode}. Sign up now: ${referralLink}`;
        const url = `https://wa.me/?text=${encodeURIComponent(message)}`;
        window.open(url, '_blank');
    });
    
    document.getElementById('shareEmail').addEventListener('click', function() {
        const subject = 'Join QuickShare - Referral Invitation';
        const body = `Hi,\n\nI'd like to invite you to join QuickShare, a great lending platform!\n\nUse my referral code: ${referralCode}\n\nSign up here: ${referralLink}\n\nBest regards`;
        const url = `mailto:?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
        window.location.href = url;
    });
    
    document.getElementById('shareFacebook').addEventListener('click', function() {
        const url = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(referralLink)}`;
        window.open(url, '_blank', 'width=600,height=400');
    });
    
    document.getElementById('shareTwitter').addEventListener('click', function() {
        const text = `Join QuickShare using my referral code: ${referralCode}. Sign up now: ${referralLink}`;
        const url = `https://twitter.com/intent/tweet?text=${encodeURIComponent(text)}`;
        window.open(url, '_blank', 'width=600,height=400');
    });
    
    // Start referring button
    document.getElementById('startReferring').addEventListener('click', function() {
        document.getElementById('copyBtn').click();
    });
    
    // Filter functionality
    const filterButtons = document.querySelectorAll('[data-filter]');
    const tableRows = document.querySelectorAll('tbody tr[data-status]');
    
    filterButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            filterButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            const filter = this.dataset.filter;
            
            tableRows.forEach(row => {
                if (filter === 'all' || row.dataset.status === filter) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });
    
    // Remind button functionality
    const remindButtons = document.querySelectorAll('.remind-btn');
    remindButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const email = this.dataset.email;
            
            // Disable button and show loading
            this.disabled = true;
            this.innerHTML = '<i class="mdi mdi-loading mdi-spin"></i>';
            
            setTimeout(() => {
                this.disabled = false;
                this.innerHTML = '<i class="mdi mdi-bell"></i>';
                showToast(`Reminder sent to ${email}`);
            }, 1500);
        });
    });
    
    // Utility functions
    function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text);
        } else {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
        }
    }
    
    function showToast(message) {
        const toast = document.createElement('div');
        toast.className = 'alert alert-success alert-dismissible fade show position-fixed';
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        toast.innerHTML = `<i class="mdi mdi-check-circle mr-2"></i>${message} <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>`;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 4000);
    }
    
    // Animate progress bar on load
    setTimeout(() => {
        const progressBar = document.querySelector('.progress-bar');
        if (progressBar) {
            progressBar.style.transition = 'width 1s ease-in-out';
        }
    }, 500);
    
    // Auto-refresh referral data every 5 minutes
    setInterval(() => {
        console.log('Refreshing referral data...');
        // In a real implementation, this would fetch updated data from the server
    }, 300000);
});
</script>
@endsection
