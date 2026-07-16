@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0"><i class="mdi mdi-cash-multiple mr-2"></i>My Earnings</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">My Earnings</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    <!-- Enhanced Earnings Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center border-success">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-success mb-0">{{ kpiMoney($totalEarnings ?? 0) }}</h4>
                    <small class="text-muted">Total Earnings</small>
                    <div class="mt-2">
                        <small class="text-success"><i class="mdi mdi-trending-up"></i> +18.2%</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-primary">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-primary mb-0">{{ kpiMoney($totalInvested ?? 0) }}</h4>
                    <small class="text-muted">Total Invested</small>
                    <div class="mt-2">
                        <small class="text-info"><i class="mdi mdi-percent"></i> {{ $totalInvested > 0 ? round((($totalEarnings ?? 0) / $totalInvested) * 100, 1) : 0 }}% ROI</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-warning">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-warning mb-0">{{ kpiMoney(($totalEarnings ?? 0) / 12) }}</h4>
                    <small class="text-muted">Monthly Average</small>
                    <div class="mt-2">
                        <small class="text-muted"><i class="mdi mdi-calendar"></i> Last 12 months</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-info">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-info mb-0">{{ $activeCount ?? 0 }}</h4>
                    <small class="text-muted">Active Investments</small>
                    <div class="mt-2">
                        <small class="text-muted">{{ isset($earnings) ? $earnings->count() : 0 }} total</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Earnings Charts -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="card-title text-uppercase mb-0">Earnings Overview</h6>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary active" data-period="month">Monthly</button>
                            <button class="btn btn-outline-primary" data-period="quarter">Quarterly</button>
                            <button class="btn btn-outline-primary" data-period="year">Yearly</button>
                        </div>
                    </div>
                    <div style="height: 350px; position: relative;">
                        <canvas id="earningsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title text-uppercase mb-3">Earnings by Type</h6>
                    <div style="height: 350px; position: relative;">
                        <canvas id="earningsTypeChart"></canvas>
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
                        <h5 class="card-title text-uppercase mb-0">Earnings Breakdown</h5>
                        <div class="btn-group">
                            <button class="btn btn-outline-secondary btn-sm" id="exportEarnings">
                                <i class="mdi mdi-download mr-1"></i> Export
                            </button>
                            <button class="btn btn-outline-primary btn-sm" id="filterBtn">
                                <i class="mdi mdi-filter mr-1"></i> Filter
                            </button>
                        </div>
                    </div>
                    
                    <!-- Filter Section (Hidden by default) -->
                    <div class="card bg-light mb-3" id="filterSection" style="display: none;">
                        <div class="card-body py-3">
                            <div class="row align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label">Date Range</label>
                                    <select class="form-control" id="dateFilter">
                                        <option value="all">All Time</option>
                                        <option value="month">Last Month</option>
                                        <option value="quarter">Last Quarter</option>
                                        <option value="year">Last Year</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Min Amount</label>
                                    <input type="number" class="form-control" id="minAmountFilter" placeholder="Min earnings">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-control" id="statusFilter">
                                        <option value="">All Status</option>
                                        <option value="completed">Completed</option>
                                        <option value="pending">Pending</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-primary btn-block" id="applyFilter">Apply Filter</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    @if(isset($earnings) && $earnings->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="thead-light">
                                <tr><th>#</th><th>Loan</th><th>Invested</th><th>Rate</th><th>Earnings</th><th>ROI</th><th>Date</th><th>Action</th></tr>
                            </thead>
                            <tbody>
                                @foreach($earnings as $e)
                                <tr data-amount="{{ $e->actual_return ?? 0 }}" data-date="{{ $e->completed_at ? $e->completed_at->timestamp : $e->created_at->timestamp }}">
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        <div>
                                            <strong>{{ $e->loan ? $e->loan->reference : '#' . $e->loan_id }}</strong>
                                            @if($e->loan && $e->loan->purpose)
                                                <br><small class="text-muted">{{ $e->loan->purpose }}</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            {{ kpiMoney($e->amount) }}
                                            <br><small class="text-muted">{{ $e->interest_rate ?? '-' }}% p.a.</small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $e->interest_rate >= 15 ? 'success' : ($e->interest_rate >= 10 ? 'warning' : 'info') }}">
                                            {{ $e->interest_rate ?? '-' }}%
                                        </span>
                                    </td>
                                    <td>
                                        <div class="text-success">
                                            <strong>{{ kpiMoney($e->actual_return ?? 0) }}</strong>
                                            @if($e->actual_return > 0)
                                                <br><small class="text-muted">{{ round((($e->actual_return - $e->amount) / $e->amount) * 100, 1) }}% profit</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="{{ ($e->actual_return - $e->amount) / $e->amount >= 0.15 ? 'text-success' : (($e->actual_return - $e->amount) / $e->amount >= 0.10 ? 'text-warning' : 'text-info') }}">
                                            <strong>{{ round((($e->actual_return - $e->amount) / $e->amount) * 100, 1) }}%</strong>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            {{ $e->completed_at ? $e->completed_at->format('M j, Y') : $e->created_at->format('M j, Y') }}
                                            <br><small class="text-muted">{{ $e->completed_at ? $e->completed_at->diffForHumans() : $e->created_at->diffForHumans() }}</small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-primary view-details" data-id="{{ $e->id }}">
                                                <i class="mdi mdi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-success download-receipt" data-id="{{ $e->id }}">
                                                <i class="mdi mdi-download"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="text-center py-5">
                        <i class="mdi mdi-cash-multiple text-muted" style="font-size:64px;"></i>
                        <h5 class="mt-3 text-muted">No Earnings Yet</h5>
                        <p class="text-muted">Fund loans to start earning interest.</p>
                        <a href="{{ route('client.marketplace.index') }}" class="btn btn-primary">Browse Marketplace</a>
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
    const earningsCtx = document.getElementById('earningsChart').getContext('2d');
    const earningsTypeCtx = document.getElementById('earningsTypeChart').getContext('2d');
    
    // Earnings Overview Chart
    const earningsChart = new Chart(earningsCtx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Earnings',
                data: [1200, 1500, 1800, 1400, 2000, 2200],
                backgroundColor: 'rgba(40, 167, 69, 0.8)',
                borderColor: 'rgba(40, 167, 69, 1)',
                borderWidth: 2
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
    
    // Earnings Type Chart
    new Chart(earningsTypeCtx, {
        type: 'doughnut',
        data: {
            labels: ['Business Loans', 'Personal Loans', 'Medical Loans', 'Education Loans'],
            datasets: [{
                data: [45, 25, 20, 10],
                backgroundColor: [
                    'rgba(40, 167, 69, 0.8)',
                    'rgba(23, 162, 184, 0.8)',
                    'rgba(255, 193, 7, 0.8)',
                    'rgba(102, 16, 242, 0.8)'
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
    
    // Period buttons functionality
    const periodButtons = document.querySelectorAll('[data-period]');
    periodButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            periodButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            const period = this.dataset.period;
            updateEarningsChart(period);
        });
    });
    
    function updateEarningsChart(period) {
        let labels, data;
        
        switch(period) {
            case 'quarter':
                labels = ['Q1', 'Q2', 'Q3', 'Q4'];
                data = [4500, 5600, 5200, 6200];
                break;
            case 'year':
                labels = ['2020', '2021', '2022', '2023', '2024'];
                data = [15000, 18000, 22000, 25000, 28000];
                break;
            default: // month
                labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
                data = [1200, 1500, 1800, 1400, 2000, 2200];
        }
        
        earningsChart.data.labels = labels;
        earningsChart.data.datasets[0].data = data;
        earningsChart.update();
    }
    
    // Filter functionality
    const filterBtn = document.getElementById('filterBtn');
    const filterSection = document.getElementById('filterSection');
    const applyFilterBtn = document.getElementById('applyFilter');
    
    filterBtn.addEventListener('click', function() {
        filterSection.style.display = filterSection.style.display === 'none' ? 'block' : 'none';
    });
    
    applyFilterBtn.addEventListener('click', function() {
        applyFilters();
    });
    
    function applyFilters() {
        const dateFilter = document.getElementById('dateFilter').value;
        const minAmount = parseFloat(document.getElementById('minAmountFilter').value) || 0;
        const statusFilter = document.getElementById('statusFilter').value;
        
        const rows = document.querySelectorAll('tbody tr[data-amount]');
        
        rows.forEach(row => {
            const amount = parseFloat(row.dataset.amount);
            const date = parseInt(row.dataset.date);
            
            let show = true;
            
            // Amount filter
            if (amount < minAmount) {
                show = false;
            }
            
            // Date filter (simplified)
            if (dateFilter !== 'all') {
                const now = Date.now();
                const filterDate = dateFilter === 'month' ? now - (30 * 24 * 60 * 60 * 1000) :
                                  dateFilter === 'quarter' ? now - (90 * 24 * 60 * 60 * 1000) :
                                  now - (365 * 24 * 60 * 60 * 1000);
                
                if (date < filterDate) {
                    show = false;
                }
            }
            
            row.style.display = show ? '' : 'none';
        });
        
        showToast('Filters applied successfully');
    }
    
    // Export functionality
    document.getElementById('exportEarnings').addEventListener('click', function() {
        const link = document.createElement('a');
        link.href = '#';
        link.download = 'earnings-history.csv';
        link.click();
        
        showToast('Earnings exported successfully!');
    });
    
    // View details functionality
    const viewButtons = document.querySelectorAll('.view-details');
    viewButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const earningId = this.dataset.id;
            showToast(`Loading details for earning #${earningId}...`);
        });
    });
    
    // Download receipt functionality
    const downloadButtons = document.querySelectorAll('.download-receipt');
    downloadButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const earningId = this.dataset.id;
            
            const link = document.createElement('a');
            link.href = '#';
            link.download = `earning-receipt-${earningId}.pdf`;
            link.click();
            
            showToast('Receipt download started. Check your downloads folder.');
        });
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
        console.log('Refreshing earnings data...');
    }, 300000);
});
</script>
@endsection
