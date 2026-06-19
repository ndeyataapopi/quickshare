@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">Loan Management</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">Loans</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    <div class="row mb-4">
        <div class="col-md-3"><div class="card"><div class="card-body"><h6 class="text-uppercase text-muted">Total Loans</h6><h3 class="text-primary">{{ App\Modules\Loans\Models\Loan::count() }}</h3></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><h6 class="text-uppercase text-muted">Pending Approval</h6><h3 class="text-warning">{{ App\Modules\Loans\Models\Loan::where('status','pending')->count() }}</h3></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><h6 class="text-uppercase text-muted">Active Loans</h6><h3 class="text-success">{{ App\Modules\Loans\Models\Loan::where('status','active')->count() }}</h3></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><h6 class="text-uppercase text-muted">Defaulted</h6><h3 class="text-danger">{{ App\Modules\Loans\Models\Loan::where('status','defaulted')->count() }}</h3></div></div></div>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">All Loans</h5>
                    <form method="GET" class="form-inline mb-4">
                        <input type="text" name="search" class="form-control mr-2 mb-2" placeholder="Search loans..." value="{{ request('search') }}">
                        <select name="status" class="form-control mr-2 mb-2">
                            <option value="">All Statuses</option>
                            @foreach(['pending','funding','active','completed','defaulted','cancelled'] as $s)
                                <option value="{{ $s }}" {{ request('status')===$s?'selected':'' }}>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-primary mb-2">Filter</button>
                    </form>

                    <div class="table-responsive">
                        <table id="config-table" class="table table-hover table-striped no-wrap border">
                            <thead class="thead-light">
                                <tr><th>Reference</th><th>Borrower</th><th>Amount</th><th>Rate</th><th>Funded</th><th>Status</th><th>Created</th><th>Action</th></tr>
                            </thead>
                            <tbody>
                                @forelse($loans as $loan)
                                <tr>
                                    <td class="font-weight-bold">{{ $loan->reference }}</td>
                                    <td>{{ $loan->borrower->first_name }} {{ $loan->borrower->last_name }}</td>
                                    <td>N$ {{ number_format($loan->amount) }}</td>
                                    <td>{{ $loan->interest_rate ?? '-' }}%</td>
                                    <td>
                                        @php $pct = $loan->amount > 0 ? min(100,round(($loan->funded_amount/$loan->amount)*100)) : 0; @endphp
                                        <div class="d-flex align-items-center">
                                            <div class="progress flex-grow-1 mr-2" style="height:6px;"><div class="progress-bar bg-info" style="width:{{ $pct }}%"></div></div>
                                            <small>{{ $pct }}%</small>
                                        </div>
                                    </td>
                                    <td>
                                        @php $bm=['pending'=>'warning','funding'=>'info','active'=>'primary','completed'=>'success','defaulted'=>'danger','cancelled'=>'secondary']; @endphp
                                        <span class="badge badge-{{ $bm[$loan->status] ?? 'secondary' }}">{{ ucfirst($loan->status) }}</span>
                                    </td>
                                    <td>{{ $loan->created_at->format('M j, Y') }}</td>
                                    <td>
                                        <a href="{{ route('admin.loans.show', $loan) }}" class="btn btn-sm btn-outline-primary">View</a>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="8" class="text-center py-4 text-muted">No loans found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection
