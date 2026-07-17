@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0"><i class="mdi mdi-trending-up mr-2"></i>My Investments</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">My Investments</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    <!-- Enhanced Portfolio Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center border-primary">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-primary mb-0">{{ kpiMoney($summary['total_invested'] ?? 0) }}</h4>
                    <small class="text-muted">Total Invested</small>
                    <div class="mt-2">
                        <small class="text-success"><i class="mdi mdi-trending-up"></i> +12.5%</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-success">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-success mb-0">{{ kpiMoney($summary['total_expected'] ?? 0) }}</h4>
                    <small class="text-muted">Expected Returns</small>
                    <div class="mt-2">
                        <small class="text-info"><i class="mdi mdi-percent"></i> {{ $summary['total_invested'] > 0 ? round((($summary['total_expected'] - $summary['total_invested']) / $summary['total_invested']) * 100, 1) : 0 }}% ROI</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-warning">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-warning mb-0">{{ kpiMoney(($summary['total_expected'] - $summary['total_invested']) ?? 0) }}</h4>
                    <small class="text-muted">Profit/Loss</small>
                    <div class="mt-2">
                        <small class="text-success"><i class="mdi mdi-arrow-up"></i> Pending</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center border-info">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-info mb-0">{{ $summary['active_count'] ?? 0 }}</h4>
                    <small class="text-muted">Active Investments</small>
                    <div class="mt-2">
                        <small class="text-muted">{{ $summary['completed_count'] ?? 0 }} completed</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Portfolio Overview Chart -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title text-uppercase mb-3">Portfolio Performance</h6>
                    <div style="height: 350px; position: relative;">
                        <canvas id="portfolioChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title text-uppercase mb-3">Investment Distribution</h6>
                    <div style="height: 350px; position: relative;">
                        <canvas id="distributionChart"></canvas>
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
                        <h5 class="card-title text-uppercase mb-0">Investment Portfolio</h5>
                        <div class="btn-group">
                            <a href="{{ route('client.marketplace.index') }}" class="btn btn-primary btn-sm">
                                <i class="mdi mdi-plus mr-1"></i> Browse Marketplace
                            </a>
                            <button class="btn btn-outline-secondary btn-sm" id="exportBtn">
                                <i class="mdi mdi-download mr-1"></i> Export
                            </button>
                        </div>
                    </div>
                    
                    <!-- Filter Tabs -->
                    <ul class="nav nav-tabs mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-filter="all" href="#">All ({{ $investments->count() }})</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-filter="active" href="#">Active ({{ $investments->where('status', 'active')->count() }})</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-filter="completed" href="#">Completed ({{ $investments->where('status', 'completed')->count() }})</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-filter="pending" href="#">Pending ({{ $investments->where('status', 'pending')->count() }})</a>
                        </li>
                    </ul>
                    @if($investments->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th><th>Loan</th><th>Invested</th><th>Rate</th><th>Expected Return</th><th>Status</th><th>Date</th><th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($investments as $investment)
                                <tr data-status="{{ $investment->status }}">
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        <div>
                                            <strong>{{ $investment->loan ? $investment->loan->reference : '—' }}</strong>
                                            @if($investment->loan && $investment->loan->purpose)
                                                <br><small class="text-muted">{{ $investment->loan->purpose }}</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong>{{ kpiMoney($investment->amount) }}</strong>
                                            @if($investment->interest_rate)
                                                <br><small class="text-muted">{{ $investment->interest_rate }}% p.a.</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-success">
                                            <strong>{{ kpiMoney($investment->expected_return) }}</strong>
                                            <br><small class="text-muted">{{ round((($investment->expected_return - $investment->amount) / $investment->amount) * 100, 1) }}%</small>
                                        </div>
                                    </td>
                                    <td>
                                        @php 
                                            $statusColors = [
                                                'pending' => 'warning',
                                                'active' => 'primary', 
                                                'completed' => 'success',
                                                'cancelled' => 'secondary'
                                            ]; 
                                            $statusIcons = [
                                                'pending' => 'clock',
                                                'active' => 'play-circle', 
                                                'completed' => 'check-circle',
                                                'cancelled' => 'close-circle'
                                            ]; 
                                        @endphp
                                        <span class="badge badge-{{ $statusColors[$investment->status] ?? 'secondary' }}">
                                            <i class="mdi mdi-{{ $statusIcons[$investment->status] ?? 'help-circle' }} mr-1"></i>
                                            {{ ucfirst($investment->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div>
                                            {{ $investment->created_at->format('M j, Y') }}
                                            @if($investment->status === 'active' && $investment->loan && $investment->loan->due_date)
                                                <br><small class="text-muted">{{ $investment->loan->due_date->diffForHumans() }}</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="{{ route('client.investments.show', $investment) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="mdi mdi-eye"></i>
                                            </a>
                                            @if($investment->status === 'completed')
                                                <button class="btn btn-sm btn-outline-success download-statement" data-id="{{ $investment->id }}">
                                                    <i class="mdi mdi-download"></i>
                                                </button>
                                            @endif
                                            @if($investment->status === 'active')
                                                <button class="btn btn-sm btn-outline-info view-schedule" data-id="{{ $investment->id }}">
                                                    <i class="mdi mdi-calendar"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $investments->links() }}</div>
                    @else
                    <div class="text-center py-5">
                        <i class="mdi mdi-trending-up text-muted" style="font-size:64px;"></i>
                        <h5 class="mt-3 text-muted">No Investments Yet</h5>
                        <p class="text-muted">Fund loans in the marketplace to start earning returns.</p>
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
    const portfolioCtx = document.getElementById('portfolioChart').getContext('2d');
    const distributionCtx = document.getElementById('distributionChart').getContext('2d');
    
    // Portfolio Performance Chart
    new Chart(portfolioCtx, {
        type: 'line',
        data: {
            labels: @json($portfolioData['labels']),
            datasets: [{
                label: 'Portfolio Value',
                data: @json($portfolioData['portfolio_value']),
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Total Invested',
                data: @json($portfolioData['total_invested']),
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.1)',
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
    
    // Investment Distribution Chart
    new Chart(distributionCtx, {
        type: 'doughnut',
        data: {
            labels: @json($distributionData['labels']),
            datasets: [{
                data: @json($distributionData['data']),
                backgroundColor: [
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(255, 99, 132, 0.8)',
                    'rgba(255, 206, 86, 0.8)',
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(153, 102, 255, 0.8)',
                    'rgba(255, 159, 64, 0.8)'
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
    
    // Filter functionality
    const filterTabs = document.querySelectorAll('[data-filter]');
    const tableRows = document.querySelectorAll('tbody tr[data-status]');
    
    filterTabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Update active tab
            filterTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            const filter = this.dataset.filter;
            
            // Filter rows
            tableRows.forEach(row => {
                if (filter === 'all' || row.dataset.status === filter) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });
    
    // Export functionality
    document.getElementById('exportBtn').addEventListener('click', function() {
        // Simulate export
        const link = document.createElement('a');
        link.href = '#';
        link.download = 'investment-portfolio.csv';
        link.click();
        
        // Show success message
        const alert = document.createElement('div');
        alert.className = 'alert alert-success alert-dismissible fade show';
        alert.innerHTML = '<i class="mdi mdi-check-circle mr-2"></i>Portfolio exported successfully! <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>';
        
        const card = document.querySelector('.card-body');
        card.insertBefore(alert, card.firstChild);
        
        setTimeout(() => {
            alert.remove();
        }, 5000);
    });
    
    // Download statement functionality
    const downloadButtons = document.querySelectorAll('.download-statement');
    downloadButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const investmentId = this.dataset.id;
            
            // Simulate download
            const link = document.createElement('a');
            link.href = '#';
            link.download = `investment-statement-${investmentId}.pdf`;
            link.click();
            
            // Show success message
            showToast('Statement download started. Check your downloads folder.');
        });
    });
    
    // View schedule functionality
    const scheduleButtons = document.querySelectorAll('.view-schedule');
    scheduleButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const investmentId = this.dataset.id;
            
            // Simulate showing schedule
            showToast('Payment schedule loaded. Showing repayment dates and amounts.');
        });
    });
    
    // Toast notification helper
    function showToast(message) {
        const toast = document.createElement('div');
        toast.className = 'alert alert-info alert-dismissible fade show position-fixed';
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        toast.innerHTML = `<i class="mdi mdi-information mr-2"></i>${message} <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>`;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 4000);
    }
    
    // Auto-refresh data every 5 minutes
    setInterval(() => {
        // In a real implementation, this would fetch updated data from the server
        console.log('Refreshing investment data...');
    }, 300000);
});
</script>
@endsection
