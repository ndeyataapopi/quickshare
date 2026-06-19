@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0"><i class="mdi mdi-account-alert mr-2"></i>Collections</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">Collections</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    <div class="row mb-3">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-primary mb-0">{{ formatKpi($stats['total']) }}</h4>
                    <small class="text-muted">Total Cases</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-warning mb-0">{{ formatKpi($stats['open']) }}</h4>
                    <small class="text-muted">Open Cases</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-success mb-0">{{ formatKpi($stats['resolved']) }}</h4>
                    <small class="text-muted">Resolved</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-info mb-0">{{ kpiMoney($stats['recovered_amount']) }}</h4>
                    <small class="text-muted">Recovered</small>
                </div>
            </div>
        </div>
    </div>
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-danger mb-0">{{ kpiMoney($stats['overdue_amount']) }}</h4>
                    <small class="text-muted">Total Overdue Amount</small>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Collections Queue</h5>
                    <form method="GET" class="form-inline mb-4">
                        <input type="text" name="search" class="form-control mr-2 mb-2" placeholder="Search by borrower or loan..." value="{{ request('search') }}">
                        <select name="status" class="form-control mr-2 mb-2">
                            <option value="">All Statuses</option>
                            <option value="open" {{ request('status')==='open'?'selected':'' }}>Open</option>
                            <option value="resolved" {{ request('status')==='resolved'?'selected':'' }}>Resolved</option>
                        </select>
                        <button type="submit" class="btn btn-primary mb-2">Filter</button>
                    </form>
                    @if($cases->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="thead-light">
                                <tr><th>Borrower</th><th>Loan</th><th>Overdue Amount</th><th>Days Overdue</th><th>Status</th><th>Action</th></tr>
                            </thead>
                            <tbody>
                                @foreach($cases as $case)
                                <tr>
                                    <td>{{ optional($case->loan->borrower)->first_name }} {{ optional($case->loan->borrower)->last_name }}</td>
                                    <td>{{ optional($case->loan)->reference }}</td>
                                    <td class="text-danger font-weight-bold">{{ kpiMoney($case->overdue_amount ?? 0) }}</td>
                                    <td>{{ $case->days_overdue ?? '-' }} days</td>
                                    <td>
                                        @php $sc = ['open'=>'warning','resolved'=>'success']; @endphp
                                        <span class="badge badge-{{ $sc[$case->status] ?? 'secondary' }}">{{ ucfirst($case->status ?? 'open') }}</span>
                                    </td>
                                    <td><a href="{{ route('admin.collections.show', $case) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $cases->links() }}</div>
                    @else
                    <div class="text-center py-5">
                        <i class="mdi mdi-account-alert text-muted" style="font-size:64px;"></i>
                        <h5 class="mt-3 text-muted">No Collections</h5>
                        <p class="text-muted">No loans are currently in collections.</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
