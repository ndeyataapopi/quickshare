@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0"><i class="mdi mdi-shield-check mr-2"></i>Trust Score</h5>
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
        $user = auth()->user();
        $scoreHistory = []; // Mock data - would come from database
        $nextTierScore = $tier === 'bronze' ? 50 : ($tier === 'silver' ? 70 : ($tier === 'gold' ? 85 : 100));
        $pointsToNext = max(0, $nextTierScore - $score);
    @endphp

    <!-- Enhanced Score Display -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-center border-{{ $tierColor }}">
                <div class="card-body py-4">
                    <div class="mb-3">
                        <span class="badge badge-{{ $tierColor }} px-3 py-2" style="font-size: 14px;">
                            <i class="mdi mdi-{{ $tier === 'bronze' ? 'medal' : ($tier === 'silver' ? 'medal-outline' : ($tier === 'gold' ? 'trophy' : 'trophy-award')) }} mr-1"></i>
                            {{ strtoupper($tier) }} TIER
                        </span>
                    </div>
                    <h1 class="display-3 font-weight-bold text-{{ $tierColor }} mb-0">{{ number_format($score, 1) }}</h1>
                    <p class="text-muted">out of 100</p>
                    <div class="progress mx-auto mb-3" style="height: 12px; max-width: 400px;">
                        <div class="progress-bar bg-{{ $tierColor }}" style="width: {{ $score }}%; transition: width 1s ease-in-out;"></div>
                    </div>
                    @if($tier !== 'platinum')
                    <div class="text-center mb-3">
                        <small class="text-muted">{{ $pointsToNext }} points to {{ $tier === 'bronze' ? 'Silver' : ($tier === 'silver' ? 'Gold' : 'Platinum') }}</small>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center border-success">
                <div class="card-body py-4">
                    <i class="mdi mdi-cash-multiple text-success" style="font-size: 48px;"></i>
                    <h4 class="mt-2 text-success">{{ kpiMoney($maxLoan) }}</h4>
                    <small class="text-muted">Max Loan Amount</small>
                    <div class="mt-2">
                        <span class="badge badge-{{ $canBorrow ? 'success' : 'danger' }}">
                            {{ $canBorrow ? 'Eligible' : 'Not Eligible' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center border-info">
                <div class="card-body py-4">
                    <i class="mdi mdi-trending-up text-info" style="font-size: 48px;"></i>
                    <h4 class="mt-2 text-info">+12.5</h4>
                    <small class="text-muted">Score Change (30 days)</small>
                    <div class="mt-2">
                        <small class="text-success"><i class="mdi mdi-arrow-up"></i> Improving</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Score History Chart -->
    <div class="row mb-4 align-items-stretch">
        <div class="col-md-8">
            <div class="card h-100">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="card-title text-uppercase mb-0">Score History</h6>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary active" data-period="month">Month</button>
                            <button class="btn btn-outline-primary" data-period="quarter">Quarter</button>
                            <button class="btn btn-outline-primary" data-period="year">Year</button>
                        </div>
                    </div>
                    <div style="flex: 1; min-height: 0; height: 350px;">
                        <canvas id="scoreHistoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body d-flex flex-column">
                    <h6 class="card-title text-uppercase mb-3">Score Factors</h6>
                    <div style="flex: 1; min-height: 0; height: 350px;">
                        <canvas id="scoreFactorsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Score Changes -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="card-title text-uppercase mb-0">Recent Score Changes</h6>
                        <button class="btn btn-sm btn-outline-primary" id="refreshScore">
                            <i class="mdi mdi-refresh"></i> Refresh
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Activity</th>
                                    <th>Points</th>
                                    <th>Score Before</th>
                                    <th>Score After</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Jun 15, 2024</td>
                                    <td>Loan repayment completed</td>
                                    <td class="text-success">+5</td>
                                    <td>72.5</td>
                                    <td class="font-weight-bold">77.5</td>
                                </tr>
                                <tr>
                                    <td>Jun 10, 2024</td>
                                    <td>On-time repayment</td>
                                    <td class="text-success">+3</td>
                                    <td>69.5</td>
                                    <td class="font-weight-bold">72.5</td>
                                </tr>
                                <tr>
                                    <td>May 28, 2024</td>
                                    <td>Referral completed</td>
                                    <td class="text-success">+2</td>
                                    <td>67.5</td>
                                    <td class="font-weight-bold">69.5</td>
                                </tr>
                                <tr>
                                    <td>May 15, 2024</td>
                                    <td>KYC verification completed</td>
                                    <td class="text-success">+10</td>
                                    <td>57.5</td>
                                    <td class="font-weight-bold">67.5</td>
                                </tr>
                                <tr>
                                    <td>May 1, 2024</td>
                                    <td>Account created</td>
                                    <td class="text-info">+50</td>
                                    <td>0</td>
                                    <td class="font-weight-bold">50.0</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Tier Breakdown</h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Tier</th>
                                    <th>Score Range</th>
                                    <th>Max Loan</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="{{ $tier === 'bronze' ? 'bg-light' : '' }}">
                                    <td>
                                        <span class="badge badge-warning">
                                            <i class="mdi mdi-medal mr-1"></i>Bronze
                                        </span>
                                    </td>
                                    <td>0 – 49</td>
                                    <td>{{ kpiMoney(5000) }}</td>
                                    <td>{{ $tier === 'bronze' ? '<span class="badge badge-dark">Current</span>' : '' }}</td>
                                </tr>
                                <tr class="{{ $tier === 'silver' ? 'bg-light' : '' }}">
                                    <td>
                                        <span class="badge badge-secondary">
                                            <i class="mdi mdi-medal-outline mr-1"></i>Silver
                                        </span>
                                    </td>
                                    <td>50 – 69</td>
                                    <td>{{ kpiMoney(15000) }}</td>
                                    <td>{!! $tier === 'silver' ? '<span class="badge badge-dark">Current</span>' : '' !!}</td>
                                </tr>
                                <tr class="{{ $tier === 'gold' ? 'bg-light' : '' }}">
                                    <td>
                                        <span class="badge badge-info">
                                            <i class="mdi mdi-trophy mr-1"></i>Gold
                                        </span>
                                    </td>
                                    <td>70 – 84</td>
                                    <td>{{ kpiMoney(50000) }}</td>
                                    <td>{!! $tier === 'gold' ? '<span class="badge badge-dark">Current</span>' : '' !!}</td>
                                </tr>
                                <tr class="{{ $tier === 'platinum' ? 'bg-light' : '' }}">
                                    <td>
                                        <span class="badge badge-primary">
                                            <i class="mdi mdi-trophy-award mr-1"></i>Platinum
                                        </span>
                                    </td>
                                    <td>85 – 100</td>
                                    <td>{{ kpiMoney(100000) }}</td>
                                    <td>{!! $tier === 'platinum' ? '<span class="badge badge-dark">Current</span>' : '' !!}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">How to Improve Your Score</h5>
                    <div class="accordion" id="improvementAccordion">
                        <div class="card">
                            <div class="card-header p-2" id="positiveHeading">
                                <h6 class="mb-0">
                                    <button class="btn btn-link btn-sm text-success w-100 text-left" data-toggle="collapse" data-target="#positiveActions">
                                        <i class="mdi mdi-plus-circle mr-2"></i>Positive Actions (+Points)
                                    </button>
                                </h6>
                            </div>
                            <div id="positiveActions" class="collapse show" aria-labelledby="positiveHeading">
                                <div class="card-body p-2">
                                    <ul class="list-unstyled mb-0 small">
                                        <li class="mb-1"><i class="mdi mdi-check-circle text-success mr-2"></i> <strong>+10</strong> — Complete KYC verification</li>
                                        <li class="mb-1"><i class="mdi mdi-check-circle text-success mr-2"></i> <strong>+3</strong> — Each on-time repayment</li>
                                        <li class="mb-1"><i class="mdi mdi-check-circle text-success mr-2"></i> <strong>+5</strong> — Fully repay a loan</li>
                                        <li class="mb-1"><i class="mdi mdi-check-circle text-success mr-2"></i> <strong>+2</strong> — Each completed referral</li>
                                        <li class="mb-1"><i class="mdi mdi-check-circle text-success mr-2"></i> <strong>+1</strong> — Active for 6+ months</li>
                                        <li class="mb-0"><i class="mdi mdi-check-circle text-success mr-2"></i> <strong>+15</strong> — No defaults for 1 year</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-header p-2" id="negativeHeading">
                                <h6 class="mb-0">
                                    <button class="btn btn-link btn-sm text-danger w-100 text-left collapsed" data-toggle="collapse" data-target="#negativeActions">
                                        <i class="mdi mdi-minus-circle mr-2"></i>Negative Actions (-Points)
                                    </button>
                                </h6>
                            </div>
                            <div id="negativeActions" class="collapse" aria-labelledby="negativeHeading">
                                <div class="card-body p-2">
                                    <ul class="list-unstyled mb-0 small">
                                        <li class="mb-1"><i class="mdi mdi-close-circle text-danger mr-2"></i> <strong>-5</strong> — Late repayment</li>
                                        <li class="mb-1"><i class="mdi mdi-close-circle text-danger mr-2"></i> <strong>-15</strong> — Loan default</li>
                                        <li class="mb-1"><i class="mdi mdi-close-circle text-danger mr-2"></i> <strong>-3</strong> — Referred user defaults</li>
                                        <li class="mb-1"><i class="mdi mdi-close-circle text-danger mr-2"></i> <strong>-1</strong> — Payment reminder sent</li>
                                        <li class="mb-0"><i class="mdi mdi-close-circle text-danger mr-2"></i> <strong>-20</strong> — Fraudulent activity</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Charts
    const scoreHistoryCtx = document.getElementById('scoreHistoryChart').getContext('2d');
    const scoreFactorsCtx = document.getElementById('scoreFactorsChart').getContext('2d');
    
    // Score History Chart
    const scoreHistoryChart = new Chart(scoreHistoryCtx, {
        type: 'line',
        data: {
            labels: ['May 1', 'May 5', 'May 10', 'May 15', 'May 20', 'May 25', 'May 30', 'Jun 5', 'Jun 10', 'Jun 15'],
            datasets: [{
                label: 'Trust Score',
                data: [50, 50, 57.5, 67.5, 69.5, 69.5, 69.5, 72.5, 72.5, 77.5],
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                tension: 0.4,
                fill: true,
                pointRadius: 4,
                pointBackgroundColor: 'rgb(75, 192, 192)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    min: 0,
                    max: 100,
                    ticks: {
                        callback: function(value) {
                            return value + '/100';
                        }
                    }
                }
            }
        }
    });
    
    // Score Factors Chart
    new Chart(scoreFactorsCtx, {
        type: 'radar',
        data: {
            labels: ['Repayments', 'KYC Status', 'Account Age', 'Referrals', 'Loan History', 'Activity'],
            datasets: [{
                label: 'Current Score',
                data: [85, 100, 60, 40, 90, 70],
                borderColor: 'rgb(54, 162, 235)',
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                pointBackgroundColor: 'rgb(54, 162, 235)',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: 'rgb(54, 162, 235)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                r: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        stepSize: 20
                    }
                }
            }
        }
    });
    
    // Period buttons for score history
    const periodButtons = document.querySelectorAll('[data-period]');
    periodButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            periodButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            const period = this.dataset.period;
            updateScoreHistoryChart(period);
        });
    });
    
    function updateScoreHistoryChart(period) {
        let labels, data;
        
        switch(period) {
            case 'quarter':
                labels = ['Apr', 'May', 'Jun'];
                data = [45, 65, 77.5];
                break;
            case 'year':
                labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
                data = [30, 35, 40, 45, 65, 77.5];
                break;
            default: // month
                labels = ['May 1', 'May 5', 'May 10', 'May 15', 'May 20', 'May 25', 'May 30', 'Jun 5', 'Jun 10', 'Jun 15'];
                data = [50, 50, 57.5, 67.5, 69.5, 69.5, 69.5, 72.5, 72.5, 77.5];
        }
        
        scoreHistoryChart.data.labels = labels;
        scoreHistoryChart.data.datasets[0].data = data;
        scoreHistoryChart.update();
    }
    
    // Refresh score button
    document.getElementById('refreshScore').addEventListener('click', function() {
        this.disabled = true;
        this.innerHTML = '<i class="mdi mdi-loading mdi-spin"></i> Refreshing...';
        
        setTimeout(() => {
            // Simulate score update
            const currentScore = {{ $score }};
            const newScore = Math.min(100, currentScore + Math.random() * 2);
            
            // Update score display
            const scoreDisplay = document.querySelector('.display-3');
            scoreDisplay.textContent = newScore.toFixed(1);
            
            // Update progress bar
            const progressBar = document.querySelector('.progress-bar');
            progressBar.style.width = newScore + '%';
            
            // Add new data point to chart
            const currentData = scoreHistoryChart.data.datasets[0].data;
            const currentLabels = scoreHistoryChart.data.labels;
            currentData.push(newScore);
            currentLabels.push('Now');
            
            // Keep only last 10 points
            if (currentData.length > 10) {
                currentData.shift();
                currentLabels.shift();
            }
            
            scoreHistoryChart.update();
            
            this.disabled = false;
            this.innerHTML = '<i class="mdi mdi-refresh"></i> Refresh';
            
            showToast('Trust score updated successfully!');
        }, 2000);
    });
    
    // Toast notification helper
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
        progressBar.style.width = '{{ $score }}%';
    }, 500);
});
</script>
@endsection
