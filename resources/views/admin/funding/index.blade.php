@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0"><i class="mdi mdi-bank-transfer-in mr-2"></i>Funding / Escrow</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">Funding</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <div class="row mb-3">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-primary mb-0">{{ formatKpi($stats['total']) }}</h4>
                    <small class="text-muted">Total</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-warning mb-0">{{ formatKpi($stats['pending']) }}</h4>
                    <small class="text-muted">Pending</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-success mb-0">{{ formatKpi($stats['confirmed']) }}</h4>
                    <small class="text-muted">Confirmed</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-danger mb-0">{{ formatKpi($stats['cancelled']) }}</h4>
                    <small class="text-muted">Cancelled</small>
                </div>
            </div>
        </div>
    </div>
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-info mb-0">{{ kpiMoney($stats['total_confirmed']) }}</h4>
                    <small class="text-muted">Total Confirmed Amount</small>
                </div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <h5 class="card-title text-uppercase mb-3">All Funding Transactions</h5>
            <form method="GET" class="form-inline mb-4">
                <input type="text" name="search" class="form-control mr-2 mb-2" placeholder="Search by reference, lender, or loan..." value="{{ request('search') }}">
                <select name="status" class="form-control mr-2 mb-2">
                    <option value="">All Statuses</option>
                    <option value="pending" {{ request('status')==='pending'?'selected':'' }}>Pending</option>
                    <option value="confirmed" {{ request('status')==='confirmed'?'selected':'' }}>Confirmed</option>
                    <option value="cancelled" {{ request('status')==='cancelled'?'selected':'' }}>Cancelled</option>
                </select>
                <button type="submit" class="btn btn-primary mb-2">Filter</button>
            </form>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Lender</th>
                            <th>Loan</th>
                            <th>Amount</th>
                            <th>Expected Return</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $t)
                        <tr>
                            <td><a href="{{ route('admin.funding.show', $t) }}">{{ $t->transaction_reference }}</a></td>
                            <td>{{ $t->lender ? $t->lender->first_name . ' ' . $t->lender->last_name : '—' }}</td>
                            <td>{{ $t->loan ? $t->loan->reference : '—' }}</td>
                            <td>N$ {{ number_format($t->amount, 2) }}</td>
                            <td>N$ {{ number_format($t->expected_return, 2) }}</td>
                            <td>
                                @php $sc=['pending'=>'warning','confirmed'=>'success','cancelled'=>'danger','refunded'=>'secondary']; @endphp
                                <span class="badge badge-{{ $sc[$t->status] ?? 'secondary' }}">{{ ucfirst($t->status) }}</span>
                            </td>
                            <td>{{ $t->created_at->format('M j, Y') }}</td>
                            <td>
                                <a href="{{ route('admin.funding.show', $t) }}" class="btn btn-xs btn-outline-primary">View</a>
                                @if($t->status === 'pending')
                                    <form method="POST" action="{{ route('admin.funding.confirm', $t) }}" class="d-inline">
                                        @csrf @method('PATCH')
                                        <button class="btn btn-xs btn-success" onclick="return confirm('Confirm this payment?')">Confirm</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No funding transactions found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $transactions->links() }}
        </div>
    </div>
</div>
@endsection
