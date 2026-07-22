@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0"><i class="mdi mdi-cash-multiple mr-2"></i>Disbursements</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">Disbursements</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row mb-3">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-primary mb-0">{{ formatKpi($stats['total']) }}</h4>
                    <small class="text-muted">Total Loans</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-warning mb-0">{{ formatKpi($stats['funded']) }}</h4>
                    <small class="text-muted">Funded</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-info mb-0">{{ formatKpi($stats['disbursed']) }}</h4>
                    <small class="text-muted">Disbursed</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-success mb-0">{{ formatKpi($stats['active']) }}</h4>
                    <small class="text-muted">Active</small>
                </div>
            </div>
        </div>
    </div>
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-info mb-0">{{ kpiMoney($stats['total_disbursed']) }}</h4>
                    <small class="text-muted">Total Disbursed Amount</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h5 class="card-title text-uppercase mb-3">All Loans</h5>
            <form method="GET" class="form-inline mb-4">
                <input type="text" name="search" class="form-control mr-2 mb-2" placeholder="Search by reference or borrower..." value="{{ request('search') }}">
                <select name="status" class="form-control mr-2 mb-2">
                    <option value="">All Statuses</option>
                    <option value="funded" {{ request('status')==='funded'?'selected':'' }}>Funded</option>
                    <option value="awaiting_disbursement" {{ request('status')==='awaiting_disbursement'?'selected':'' }}>Awaiting Disbursement</option>
                    <option value="disbursed" {{ request('status')==='disbursed'?'selected':'' }}>Disbursed</option>
                    <option value="active" {{ request('status')==='active'?'selected':'' }}>Active</option>
                </select>
                <button type="submit" class="btn btn-primary mb-2">Filter</button>
            </form>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Borrower</th>
                            <th>Amount</th>
                            <th>Term</th>
                            <th>Status</th>
                            <th>Disbursed</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($loans as $loan)
                        <tr>
                            <td><a href="{{ route('admin.disbursements.show', $loan) }}">{{ $loan->reference }}</a></td>
                            <td>{{ $loan->borrower->first_name }} {{ $loan->borrower->last_name }}</td>
                            <td class="font-weight-bold">{{ kpiMoney($loan->approved_amount ?? $loan->requested_amount) }}</td>
                            <td>{{ $loan->loan_term_days }} days</td>
                            <td>
                                @php $sc = ['funded'=>'warning','awaiting_disbursement'=>'info','disbursed'=>'info','active'=>'success']; @endphp
                                <span class="badge badge-{{ $sc[$loan->status] ?? 'secondary' }}">{{ ucfirst(str_replace('_', ' ', $loan->status)) }}</span>
                            </td>
                            <td>{{ $loan->disbursed_at ? \Carbon\Carbon::parse($loan->disbursed_at)->format('M j, Y') : '—' }}</td>
                            <td>
                                @if($loan->status === 'funded')
                                    <a href="{{ route('admin.disbursements.show', $loan) }}" class="btn btn-sm btn-primary">
                                        <i class="mdi mdi-send mr-1"></i> Disburse
                                    </a>
                                @elseif($loan->status === 'awaiting_disbursement')
                                    <form method="POST" action="{{ route('admin.disbursements.confirm', $loan) }}" class="d-inline">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Confirm disbursement?')">
                                            <i class="mdi mdi-check mr-1"></i> Confirm
                                        </button>
                                    </form>
                                @else
                                    <a href="{{ route('admin.disbursements.show', $loan) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="mdi mdi-eye mr-1"></i> View
                                    </a>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No loans found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $loans->links() }}</div>
        </div>
    </div>
</div>
@endsection
