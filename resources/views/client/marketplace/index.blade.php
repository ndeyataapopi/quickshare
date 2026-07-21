@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0"><i class="mdi mdi-store mr-2"></i>Marketplace</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">Marketplace</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show"><i class="mdi mdi-check-circle mr-2"></i>{{ session('success') }}<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show"><i class="mdi mdi-alert-circle mr-2"></i>{{ session('error') }}<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>
    @endif
    
    <!-- Marketplace Stats -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-primary mb-0">{{ formatKpi($loans->count()) }}</h4>
                    <small class="text-muted">Available Loans</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-success mb-0">{{ formatKpi($loans->whereIn('status', ['marketplace', 'partially_funded'])->count()) }}</h4>
                    <small class="text-muted">Active Funding</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-info mb-0">{{ kpiMoney($loans->sum('requested_amount')) }}</h4>
                    <small class="text-muted">Total Volume</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-warning mb-0">{{ config('loans.min_funding_amount', 500) }}</h4>
                    <small class="text-muted">Min Investment</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select class="form-control filter-select" data-filter="status">
                                <option value="">All Status</option>
                                <option value="marketplace">Marketplace</option>
                                <option value="partially_funded">Partially Funded</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Purpose</label>
                            <select class="form-control filter-select" data-filter="purpose">
                                <option value="">All Purposes</option>
                                <option value="Medical Expenses">Medical</option>
                                <option value="Business">Business</option>
                                <option value="Education">Education</option>
                                <option value="Home Repairs">Home</option>
                                <option value="Emergency">Emergency</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Sort By</label>
                            <select class="form-control sort-select">
                                <option value="newest">Newest First</option>
                                <option value="oldest">Oldest First</option>
                                <option value="amount-high">Amount: High to Low</option>
                                <option value="amount-low">Amount: Low to High</option>
                                <option value="funding">Most Funded</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-outline-secondary btn-block reset-filters">
                                <i class="mdi mdi-refresh mr-2"></i>Reset Filters
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row loan-grid">
        @forelse($loans as $loan)
        <div class="col-md-6 col-lg-4 loan-card" data-status="{{ $loan->status }}" data-purpose="{{ $loan->purpose }}" data-amount="{{ $loan->display['loan_amount'] }}" data-funded="{{ $loan->display['funded_amount'] }}" data-date="{{ $loan->created_at->timestamp }}">
            <div class="card h-100">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h6 class="card-title mb-0">{{ $loan->reference }}</h6>
                            <small class="text-muted">{{ $loan->created_at->format('M j, Y') }}</small>
                        </div>
                        @php
                            $statusColors = [
                                'marketplace' => 'primary',
                                'partially_funded' => 'info',
                                'funded' => 'success',
                                'approved' => 'success',
                                'active' => 'info',
                                'completed' => 'secondary'
                            ];
                            $statusColor = $statusColors[$loan->status] ?? 'secondary';
                        @endphp
                        <span class="badge badge-{{ $statusColor }}">{{ ucfirst($loan->status) }}</span>
                    </div>
                    
                    @php
                        $minFund = config('loans.min_funding_amount', 500);
                    @endphp
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-end">
                            <h4 class="text-primary mb-1">{{ kpiMoney($loan->display['loan_amount']) }}</h4>
                            <span class="text-muted small">{{ $loan->loan_term_days }} days</span>
                        </div>
                        <p class="text-muted small mb-2">{{ $loan->purpose }}</p>

                        <!-- Trust Score & Risk -->
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div>
                                <small class="text-muted d-block">Trust Score</small>
                                <span class="badge badge-info badge-sm">{{ number_format($loan->display['trust_score'], 0) }}/100</span>
                            </div>
                            <div class="text-right">
                                <small class="text-muted d-block">Risk</small>
                                <span class="badge badge-{{ $loan->display['risk_color'] }} badge-sm">
                                    <i class="mdi mdi-shield-check mr-1"></i>{{ ucfirst($loan->display['risk_level']) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="row small text-muted mb-2">
                        <div class="col-6">
                            Flat Fee: {{ kpiMoney($loan->display['total_loan_charge']) }}
                        </div>
                        <div class="col-6">
                            <i class="mdi mdi-cash-multiple"></i> Exp. Return: {{ kpiMoney($loan->display['expected_return']) }}
                        </div>
                    </div>
                    <div class="row small text-muted mb-2">
                        <div class="col-6">
                            Platform Fee: {{ kpiMoney($loan->display['platform_fee']) }}
                        </div>
                        <div class="col-6">
                            Lender Return: {{ kpiMoney($loan->display['lender_return']) }}
                        </div>
                    </div>
                    <div class="row small text-muted mb-2">
                        <div class="col-6">
                            Borrower Repayment: {{ kpiMoney($loan->display['borrower_repayment']) }}
                        </div>
                        <div class="col-6">
                            Exp. Profit: {{ kpiMoney($loan->display['expected_profit']) }}
                        </div>
                    </div>
                    
                    <!-- Funding Progress -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Funded</span>
                            <span class="font-weight-bold">{{ $loan->display['progress_percent'] }}%</span>
                        </div>
                        <div class="progress" style="height:8px;">
                            <div class="progress-bar bg-{{ $loan->display['progress_percent'] >= 75 ? 'success' : ($loan->display['progress_percent'] >= 50 ? 'warning' : 'info') }}" 
                                 style="width:{{ $loan->display['progress_percent'] }}%; transition: width 0.3s ease;"></div>
                        </div>
                        <div class="d-flex justify-content-between small text-muted mt-1">
                            <span>{{ kpiMoney($loan->display['funded_amount']) }}</span>
                            <span>{{ kpiMoney($loan->display['remaining_amount']) }} left</span>
                        </div>
                    </div>
                    
                    <!-- Funding Form -->
                    @if($loan->isOnMarketplace() && $loan->display['remaining_amount'] > 0)
                    <form action="{{ route('client.marketplace.fund', $loan) }}" method="POST" class="funding-form mt-auto">
                        @csrf
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text">{{ config('loans.currency_symbol') }}</span>
                            </div>
                            <input type="number" name="amount" class="form-control"
                                   placeholder="{{ $minFund }}"
                                   min="{{ $minFund }}" 
                                   max="{{ $loan->display['remaining_amount'] }}" 
                                   step="0.01" 
                                   required>
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-primary">
                                    <i class="mdi mdi-plus"></i> Fund
                                </button>
                            </div>
                        </div>
                        <small class="text-muted">Min: {{ config('loans.currency_symbol') }}{{ number_format($minFund) }}</small>
                    </form>
                    @else
                    <div class="mt-auto">
                        <button class="btn btn-outline-secondary btn-sm btn-block" disabled>
                            @if($loan->status === 'completed')
                                <i class="mdi mdi-check mr-1"></i> Fully Funded
                            @else
                                <i class="mdi mdi-lock mr-1"></i> Not Available
                            @endif
                        </button>
                    </div>
                    @endif
                    
                    <!-- Quick Actions -->
                    <div class="mt-2 text-center">
                        <button class="btn btn-link btn-sm text-primary view-details"
                                data-id="{{ $loan->id }}"
                                data-url="{{ route('client.marketplace.show', $loan) }}">
                            <i class="mdi mdi-eye mr-1"></i>View Details
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="mdi mdi-tune text-muted" style="font-size:64px;"></i>
                    <h5 class="mt-3 text-muted">No Listings Available</h5>
                    <p class="text-muted">No loans are available for funding at the moment.</p>
                </div>
            </div>
        </div>
        @endforelse
    </div>
    @if(method_exists($loans, 'links'))
    <div class="mt-3">{{ $loans->links() }}</div>
    @endif
</div>

<!-- Loan Details Modal -->
<div class="modal fade" id="loanDetailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Loan Details</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="loanDetailsContent">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const loanCards = document.querySelectorAll('.loan-card');
    const filterSelects = document.querySelectorAll('.filter-select');
    const sortSelect = document.querySelector('.sort-select');
    const resetButton = document.querySelector('.reset-filters');
    
    // Filter functionality
    function applyFilters() {
        const statusFilter = document.querySelector('[data-filter="status"]').value;
        const purposeFilter = document.querySelector('[data-filter="purpose"]').value;
        
        loanCards.forEach(card => {
            const status = card.dataset.status;
            const purpose = card.dataset.purpose;
            
            let show = true;
            
            if (statusFilter && status !== statusFilter) {
                show = false;
            }
            
            if (purposeFilter && purpose !== purposeFilter) {
                show = false;
            }
            
            card.style.display = show ? '' : 'none';
        });
    }
    
    // Sort functionality
    function applySorting() {
        const sortBy = sortSelect.value;
        const container = document.querySelector('.loan-grid');
        const cards = Array.from(container.querySelectorAll('.loan-card'));
        
        cards.sort((a, b) => {
            let aValue, bValue;
            
            switch(sortBy) {
                case 'newest':
                    aValue = parseInt(b.dataset.date);
                    bValue = parseInt(a.dataset.date);
                    break;
                case 'oldest':
                    aValue = parseInt(a.dataset.date);
                    bValue = parseInt(b.dataset.date);
                    break;
                case 'amount-high':
                    aValue = parseFloat(b.dataset.amount);
                    bValue = parseFloat(a.dataset.amount);
                    break;
                case 'amount-low':
                    aValue = parseFloat(a.dataset.amount);
                    bValue = parseFloat(b.dataset.amount);
                    break;
                case 'funding':
                    aValue = parseFloat(b.dataset.funded) / parseFloat(b.dataset.amount);
                    bValue = parseFloat(a.dataset.funded) / parseFloat(a.dataset.amount);
                    break;
                default:
                    return 0;
            }
            
            return aValue - bValue;
        });
        
        // Re-append sorted cards
        cards.forEach(card => container.appendChild(card));
    }
    
    // Event listeners
    filterSelects.forEach(select => {
        select.addEventListener('change', applyFilters);
    });
    
    sortSelect.addEventListener('change', applySorting);
    
    resetButton.addEventListener('click', function() {
        filterSelects.forEach(select => select.value = '');
        sortSelect.value = 'newest';
        applyFilters();
        applySorting();
    });
    
    // View details functionality
    const viewDetailsButtons = document.querySelectorAll('.view-details');
    const loanDetailsModal = $('#loanDetailsModal');
    const loanDetailsContent = document.getElementById('loanDetailsContent');
    const currencySymbol = {{ json_encode(config('loans.currency_symbol')) }};
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function formatMoney(amount) {
        return currencySymbol + ' ' + parseFloat(amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function riskBadgeClass(level) {
        return level === 'low' ? 'success' : (level === 'medium' ? 'warning' : 'danger');
    }

    viewDetailsButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const url = this.dataset.url;

            loanDetailsContent.innerHTML = `
                <div class="text-center py-4">
                    <i class="mdi mdi-loading mdi-spin" style="font-size: 48px;"></i>
                    <p class="mt-2">Loading loan details...</p>
                </div>
            `;

            fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => {
                if (! response.ok) throw new Error('Could not load loan details.');
                return response.json();
            })
            .then(data => {
                const l = data.listing || data;
                const borrower = l.borrower || {};
                const loan = l.loan || {};
                const funding = l.funding || {};
                const remaining = parseFloat(funding.remaining_amount || 0);
                const progress = funding.progress_percent || 0;
                const canFund = ['marketplace', 'partially_funded'].includes(funding.status) && remaining > 0;
                const fundUrl = url + '/fund';
                const minFund = {{ config('loans.min_funding_amount', 500) }};

                const riskClass = riskBadgeClass(borrower.risk_level);
                const expectedReturn = loan.expected_return || 0;
                const expectedProfit = loan.expected_profit || 0;
                const scheduleRows = (loan.repayment_schedule || []).map(row => `
                    <tr><td>Installment ${row.installment}</td><td>${row.due_date}</td><td>${formatMoney(row.amount)}</td></tr>
                `).join('');
                const historyRows = (l.funding_history || []).length
                    ? (l.funding_history || []).map(row => `
                        <tr><td>${row.lender_hash || 'Lender'}</td><td>${formatMoney(row.amount)}</td><td>${row.confirmed_at || '-'}</td></tr>
                    `).join('')
                    : '<tr><td colspan="3" class="text-muted text-center">No confirmed contributions yet.</td></tr>';

                loanDetailsContent.innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Borrower Information</h6>
                            <table class="table table-sm">
                                <tr><td>Borrower Name:</td><td>${borrower.name || '-'}</td></tr>
                                <tr><td>Trust Score:</td><td><span class="badge badge-info">${borrower.trust_score || 0}/100</span></td></tr>
                                <tr><td>Trust Tier:</td><td>${ucfirst(borrower.trust_tier || '-')}</td></tr>
                                <tr><td>Risk Level:</td><td><span class="badge badge-${riskClass}">${ucfirst(borrower.risk_level || 'high')}</span></td></tr>
                                <tr><td>Repayment Probability:</td><td>${borrower.repayment_probability || 0}%</td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Loan Details</h6>
                            <table class="table table-sm">
                                <tr><td>Reference:</td><td>${l.reference || '-'}</td></tr>
                                <tr><td>Purpose:</td><td>${loan.purpose || '-'}</td></tr>
                                <tr><td>Loan Amount:</td><td>${formatMoney(loan.approved_amount)}</td></tr>
                                <tr><td>Term:</td><td>${loan.loan_term_days || 0} days</td></tr>
                                <tr><td>Flat Fee:</td><td>${formatMoney(loan.total_loan_charge || 0)}</td></tr>
                                <tr><td>Platform Fee:</td><td>${formatMoney(loan.platform_fee)}</td></tr>
                                <tr><td>Lender Return:</td><td class="text-success">${formatMoney(loan.lender_return || 0)}</td></tr>
                                <tr><td>Expected Return:</td><td class="text-success">${formatMoney(expectedReturn || 0)}</td></tr>
                                <tr><td>Expected Profit:</td><td class="text-success">${formatMoney(expectedProfit || 0)}</td></tr>
                                <tr><td>Borrower Repayment:</td><td>${formatMoney(loan.borrower_repayment || loan.total_repayment || 0)}</td></tr>
                            </table>
                        </div>
                    </div>
                    <div class="mt-3">
                        <h6>Funding Progress</h6>
                        <div class="progress mb-2" style="height: 20px;">
                            <div class="progress-bar bg-${progress >= 75 ? 'success' : (progress >= 50 ? 'warning' : 'info')}" style="width: ${progress}%;">${progress}%</div>
                        </div>
                        <small class="text-muted">${formatMoney(funding.funded_amount)} funded &bull; ${formatMoney(remaining)} remaining</small>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <h6>Repayment Schedule</h6>
                            <table class="table table-sm table-striped">
                                <thead><tr><th>Installment</th><th>Due Date</th><th>Amount</th></tr></thead>
                                <tbody>${scheduleRows}</tbody>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Funding History</h6>
                            <table class="table table-sm table-striped">
                                <thead><tr><th>Lender</th><th>Amount</th><th>Confirmed</th></tr></thead>
                                <tbody>${historyRows}</tbody>
                            </table>
                        </div>
                    </div>
                    ${canFund ? `
                    <div class="mt-4">
                        <h6>Fund This Loan</h6>
                        <form action="${fundUrl}" method="POST" class="funding-form">
                            <input type="hidden" name="_token" value="${csrfToken}">
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text">${currencySymbol}</span></div>
                                <input type="number" name="amount" class="form-control" placeholder="${minFund}" min="${minFund}" max="${remaining.toFixed(2)}" step="0.01" required>
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-primary"><i class="mdi mdi-plus"></i> Fund</button>
                                </div>
                            </div>
                            <small class="text-muted">Min: ${currencySymbol}${minFund}</small>
                        </form>
                    </div>
                    ` : '<div class="alert alert-secondary mt-3 mb-0">This loan is not currently available for funding.</div>'}
                `;

                attachFundingValidation(loanDetailsContent);
            })
            .catch(error => {
                loanDetailsContent.innerHTML = `
                    <div class="alert alert-danger">${error.message || 'Unable to load loan details.'}</div>
                `;
            });

            loanDetailsModal.modal('show');
        });
    });

    function ucfirst(string) {
        return (string || '').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
    }

    function attachFundingValidation(container) {
        container.querySelectorAll('.funding-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const input = this.querySelector('input[name="amount"]');
                const amount = parseFloat(input.value);
                const maxAmount = parseFloat(input.max);
                const minAmount = parseFloat(input.min);

                if (amount < minAmount) {
                    e.preventDefault();
                    alert(`Minimum investment is ${currencySymbol}${minAmount}`);
                    return;
                }

                if (amount > maxAmount) {
                    e.preventDefault();
                    alert(`Maximum investment is ${currencySymbol}${maxAmount.toFixed(2)}`);
                    return;
                }

                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="mdi mdi-loading mdi-spin"></i>';
                }
            });
        });
    }
    
    // Funding form validation
    attachFundingValidation(document);
    
    // Auto-refresh funding progress every 30 seconds
    setInterval(() => {
        // In a real implementation, this would fetch updated data from the server
        console.log('Refreshing funding progress...');
    }, 30000);
});
</script>
@endsection
