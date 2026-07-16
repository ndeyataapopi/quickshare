@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0"><i class="mdi mdi-chart-line mr-2"></i>Analytics</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">Analytics</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    @php
        $user = auth()->user();
        $totalLoans      = $user->loans()->count();
        $activeLoans     = $user->loans()->whereIn('status', ['active', 'disbursed'])->count();
        $completedLoans  = $user->loans()->where('status', 'completed')->count();
        $defaultedLoans  = $user->loans()->where('status', 'defaulted')->count();
        $totalBorrowed   = $user->loans()->whereNotNull('approved_amount')->sum('approved_amount');
        $totalRepaid     = $user->repayments()->where('status', 'completed')->sum('amount');
        $totalInvested   = $user->fundingTransactions()->where('status', 'confirmed')->sum('amount');
        $totalExpected   = $user->fundingTransactions()->where('status', 'confirmed')->sum('expected_return');
        $score           = (float) $user->trust_score;
        $tier            = \App\Modules\TrustScore\Services\TrustScoreService::getTier($score);
        $totalEarnings   = $totalExpected - $totalInvested;
        $repaymentRate   = $totalLoans > 0 ? round(($completedLoans / $totalLoans) * 100, 1) : 0;
    @endphp

    <!-- Enhanced Analytics Stats -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card text-center border-primary">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-primary mb-0">{{ $totalLoans }}</h4>
                    <small class="text-muted">Total Loans</small>
                    <div class="mt-2">
                        <small class="text-success">{{ $activeLoans }} active</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card text-center border-success">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-success mb-0">{{ kpiMoney($totalBorrowed) }}</h4>
                    <small class="text-muted">Total Borrowed</small>
                    <div class="mt-2">
                        <small class="text-info">{{ $repaymentRate }}% repayment rate</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card text-center border-warning">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-warning mb-0">{{ kpiMoney($totalInvested) }}</h4>
                    <small class="text-muted">Total Invested</small>
                    <div class="mt-2">
                        <small class="text-success">{{ kpiMoney($totalEarnings) }} earnings</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card text-center border-info">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-info mb-0">{{ number_format($score, 1) }}</h4>
                    <small class="text-muted">Trust Score</small>
                    <div class="mt-2">
                        <span class="badge badge-{{ $score >= 80 ? 'success' : ($score >= 60 ? 'warning' : 'danger') }}">
                            {{ ucfirst($tier) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Analytics Charts -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="card-title text-uppercase mb-0">Financial Overview</h6>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary active" data-chart="overview" data-period="month">Month</button>
                            <button class="btn btn-outline-primary" data-chart="overview" data-period="quarter">Quarter</button>
                            <button class="btn btn-outline-primary" data-chart="overview" data-period="year">Year</button>
                        </div>
                    </div>
                    <div style="height: 350px; position: relative;">
                        <canvas id="overviewChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title text-uppercase mb-3">Loan Status Distribution</h6>
                    <div style="height: 350px; position: relative;">
                        <canvas id="loanStatusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="card-title text-uppercase mb-0">Trust Score History</h6>
                        <button class="btn btn-sm btn-outline-info" id="refreshTrustScore">
                            <i class="mdi mdi-refresh"></i>
                        </button>
                    </div>
                    <div style="height: 350px; position: relative;">
                        <canvas id="trustScoreChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title text-uppercase mb-3">Investment Performance</h6>
                    <div style="height: 350px; position: relative;">
                        <canvas id="investmentChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title text-uppercase mb-0">Borrower Summary</h5>
                        <span class="badge badge-{{ $repaymentRate >= 90 ? 'success' : ($repaymentRate >= 70 ? 'warning' : 'danger') }}">
                            {{ $repaymentRate }}% Repayment Rate
                        </span>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="text-center mb-3">
                                <h4 class="text-primary mb-1">{{ kpiMoney($totalBorrowed) }}</h4>
                                <small class="text-muted">Total Borrowed</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center mb-3">
                                <h4 class="text-success mb-1">{{ kpiMoney($totalRepaid) }}</h4>
                                <small class="text-muted">Total Repaid</small>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <tbody>
                                <tr>
                                    <td class="text-muted">Active Loans</td>
                                    <td class="font-weight-bold">{{ $activeLoans }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Completed Loans</td>
                                    <td class="font-weight-bold text-success">{{ $completedLoans }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Defaulted Loans</td>
                                    <td class="font-weight-bold {{ $defaultedLoans > 0 ? 'text-danger' : '' }}">{{ $defaultedLoans }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Outstanding</td>
                                    <td class="font-weight-bold text-warning">{{ kpiMoney($totalBorrowed - $totalRepaid) }}</td>
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
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title text-uppercase mb-0">Lender Summary</h5>
                        <span class="badge badge-{{ $totalEarnings >= 0 ? 'success' : 'danger' }}">
                            {{ $totalEarnings >= 0 ? 'Profit' : 'Loss' }}
                        </span>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="text-center mb-3">
                                <h4 class="text-warning mb-1">{{ kpiMoney($totalInvested) }}</h4>
                                <small class="text-muted">Total Invested</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center mb-3">
                                <h4 class="text-success mb-1">{{ kpiMoney($totalExpected) }}</h4>
                                <small class="text-muted">Expected Returns</small>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <tbody>
                                <tr>
                                    <td class="text-muted">Expected Profit</td>
                                    <td class="font-weight-bold text-success">{{ kpiMoney($totalEarnings) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">ROI</td>
                                    <td class="font-weight-bold text-info">{{ $totalInvested > 0 ? round(($totalEarnings / $totalInvested) * 100, 1) : 0 }}%</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Trust Score</td>
                                    <td class="font-weight-bold">{{ number_format($score, 1) }}/100</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Trust Tier</td>
                                    <td><span class="badge badge-{{ $score >= 80 ? 'success' : ($score >= 60 ? 'warning' : 'danger') }}">{{ ucfirst($tier) }}</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title text-uppercase mb-0">Recent Activity</h5>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary active" data-activity="loans">Loans</button>
                            <button class="btn btn-outline-primary" data-activity="investments">Investments</button>
                            <button class="btn btn-outline-primary" data-activity="repayments">Repayments</button>
                        </div>
                    </div>
                    @php $recentLoans = $user->loans()->latest()->take(5)->get(); @endphp
                    @if($recentLoans->isEmpty())
                        <div class="text-center py-4">
                            <i class="mdi mdi-cash-usd text-muted" style="font-size:48px;"></i>
                            <p class="text-muted mt-2">No activity yet. <a href="{{ route('client.loans.create') }}">Apply for a loan</a> or <a href="{{ route('client.marketplace.index') }}">browse investments</a>.</p>
                        </div>
                    @else
                    <div class="table-responsive">
                        <table class="table table-hover activity-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Reference</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentLoans as $loan)
                                <tr data-activity="loans">
                                    <td>
                                        <span class="badge badge-primary">Loan</span>
                                    </td>
                                    <td>
                                        <strong>{{ $loan->reference }}</strong>
                                        @if($loan->purpose)
                                            <br><small class="text-muted">{{ $loan->purpose }}</small>
                                        @endif
                                    </td>
                                    <td>{{ kpiMoney($loan->requested_amount) }}</td>
                                    <td>
                                        <span class="badge badge-{{ ['pending_review'=>'warning','marketplace'=>'info','active'=>'primary','completed'=>'success','defaulted'=>'danger','cancelled'=>'secondary'][$loan->status] ?? 'secondary' }}">
                                            {{ ucwords(str_replace('_',' ',$loan->status)) }}
                                        </span>
                                    </td>
                                    <td>
                                        {{ $loan->created_at->format('M j, Y') }}
                                        <br><small class="text-muted">{{ $loan->created_at->diffForHumans() }}</small>
                                    </td>
                                    <td>
                                        <a href="{{ route('client.loans.show', $loan) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="mdi mdi-eye"></i>
                                        </a>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Charts
    const overviewCtx = document.getElementById('overviewChart').getContext('2d');
    const loanStatusCtx = document.getElementById('loanStatusChart').getContext('2d');
    const trustScoreCtx = document.getElementById('trustScoreChart').getContext('2d');
    const investmentCtx = document.getElementById('investmentChart').getContext('2d');
    
    // Financial Overview Chart
    const overviewChart = new Chart(overviewCtx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Borrowed',
                data: [5000, 8000, 12000, 10000, 15000, 18000],
                borderColor: 'rgb(54, 162, 235)',
                backgroundColor: 'rgba(54, 162, 235, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Invested',
                data: [3000, 5000, 7000, 9000, 12000, 14000],
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Earnings',
                data: [200, 350, 500, 800, 1200, 1500],
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'N$ ' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    
    // Loan Status Distribution Chart
    new Chart(loanStatusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Active', 'Completed', 'Pending', 'Defaulted'],
            datasets: [{
                data: [{{ $activeLoans }}, {{ $completedLoans }}, 2, {{ $defaultedLoans }}],
                backgroundColor: [
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(255, 206, 86, 0.8)',
                    'rgba(255, 99, 132, 0.8)'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        }
    });
    
    // Trust Score History Chart
    const trustScoreChart = new Chart(trustScoreCtx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Trust Score',
                data: [65, 68, 72, 75, 78, {{ $score }}],
                borderColor: 'rgb(153, 102, 255)',
                backgroundColor: 'rgba(153, 102, 255, 0.1)',
                tension: 0.4,
                fill: true
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
    
    // Investment Performance Chart
    new Chart(investmentCtx, {
        type: 'bar',
        data: {
            labels: ['Q1', 'Q2', 'Q3', 'Q4'],
            datasets: [{
                label: 'Invested',
                data: [3000, 4000, 3500, 5000],
                backgroundColor: 'rgba(255, 159, 64, 0.8)',
                borderColor: 'rgba(255, 159, 64, 1)',
                borderWidth: 2
            }, {
                label: 'Returns',
                data: [3200, 4400, 3850, 5500],
                backgroundColor: 'rgba(75, 192, 192, 0.8)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'N$ ' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    
    // Period buttons for overview chart
    const periodButtons = document.querySelectorAll('[data-chart="overview"]');
    periodButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            periodButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            const period = this.dataset.period;
            updateOverviewChart(period);
        });
    });
    
    function updateOverviewChart(period) {
        let labels, borrowedData, investedData, earningsData;
        
        switch(period) {
            case 'quarter':
                labels = ['Q1', 'Q2', 'Q3', 'Q4'];
                borrowedData = [15000, 22000, 18000, 25000];
                investedData = [10000, 15000, 12000, 18000];
                earningsData = [800, 1500, 1200, 2000];
                break;
            case 'year':
                labels = ['2020', '2021', '2022', '2023', '2024'];
                borrowedData = [30000, 45000, 60000, 75000, 90000];
                investedData = [20000, 30000, 40000, 55000, 70000];
                earningsData = [2000, 3500, 5000, 7000, 9000];
                break;
            default: // month
                labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
                borrowedData = [5000, 8000, 12000, 10000, 15000, 18000];
                investedData = [3000, 5000, 7000, 9000, 12000, 14000];
                earningsData = [200, 350, 500, 800, 1200, 1500];
        }
        
        overviewChart.data.labels = labels;
        overviewChart.data.datasets[0].data = borrowedData;
        overviewChart.data.datasets[1].data = investedData;
        overviewChart.data.datasets[2].data = earningsData;
        overviewChart.update();
    }
    
    // Activity tabs
    const activityButtons = document.querySelectorAll('[data-activity]');
    const activityRows = document.querySelectorAll('.activity-table tbody tr[data-activity]');
    
    activityButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            activityButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            const activity = this.dataset.activity;
            
            // Filter activity rows (simplified - in real app would fetch different data)
            activityRows.forEach(row => {
                row.style.display = activity === 'loans' ? '' : 'none';
            });
            
            if (activity !== 'loans') {
                // Show loading message for other tabs
                const tbody = document.querySelector('.activity-table tbody');
                if (tbody.querySelector('.loading-message')) return;
                
                const loadingRow = document.createElement('tr');
                loadingRow.className = 'loading-message';
                loadingRow.innerHTML = `
                    <td colspan="6" class="text-center py-4">
                        <i class="mdi mdi-loading mdi-spin"></i>
                        <p class="mt-2 text-muted">Loading ${activity} data...</p>
                    </td>
                `;
                tbody.appendChild(loadingRow);
                
                setTimeout(() => {
                    loadingRow.remove();
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <p class="text-muted">No ${activity} activity in the selected period.</p>
                            </td>
                        </tr>
                    `;
                }, 1000);
            }
        });
    });
    
    // Refresh Trust Score button
    document.getElementById('refreshTrustScore').addEventListener('click', function() {
        this.disabled = true;
        this.innerHTML = '<i class="mdi mdi-loading mdi-spin"></i>';
        
        setTimeout(() => {
            // Simulate score update
            const newScore = Math.min(100, {{ $score }} + Math.random() * 2);
            const currentData = trustScoreChart.data.datasets[0].data;
            currentData[currentData.length - 1] = newScore;
            trustScoreChart.update();
            
            this.disabled = false;
            this.innerHTML = '<i class="mdi mdi-refresh"></i>';
            
            showToast('Trust score updated successfully!');
        }, 1500);
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
    
    // Auto-refresh data every 5 minutes
    setInterval(() => {
        console.log('Refreshing analytics data...');
        // In a real implementation, this would fetch updated data from the server
    }, 300000);
});
</script>
@endsection
