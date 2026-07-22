@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0"><i class="mdi mdi-history mr-2"></i>Audit Logs</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">Audit Logs</li>
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
                    <small class="text-muted">Total Logs</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-success mb-0">{{ formatKpi($stats['today']) }}</h4>
                    <small class="text-muted">Today</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-info mb-0">{{ formatKpi($stats['this_week']) }}</h4>
                    <small class="text-muted">This Week</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-warning mb-0">{{ formatKpi($stats['this_month']) }}</h4>
                    <small class="text-muted">This Month</small>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">System Audit Logs</h5>
                    <form method="GET" class="mb-4">
                        <div class="form-row">
                            <div class="col-md-3 mb-2">
                                <input type="text" name="search" class="form-control" placeholder="Search by user, event, or model..." value="{{ request('search') }}">
                            </div>
                            <div class="col-md-2 mb-2">
                                <select name="source" class="form-control">
                                    <option value="">All Sources</option>
                                    <option value="audit" {{ request('source')==='audit'?'selected':'' }}>Model Audit</option>
                                    <option value="activity" {{ request('source')==='activity'?'selected':'' }}>Activity Log</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <select name="event" class="form-control">
                                    <option value="">All Events</option>
                                    <option value="created" {{ request('event')==='created'?'selected':'' }}>Created</option>
                                    <option value="updated" {{ request('event')==='updated'?'selected':'' }}>Updated</option>
                                    <option value="deleted" {{ request('event')==='deleted'?'selected':'' }}>Deleted</option>
                                    <option value="loan" {{ request('event')==='loan'?'selected':'' }}>Loan Events</option>
                                    <option value="funding" {{ request('event')==='funding'?'selected':'' }}>Funding Events</option>
                                    <option value="kyc" {{ request('event')==='kyc'?'selected':'' }}>KYC Events</option>
                                    <option value="user" {{ request('event')==='user'?'selected':'' }}>User/Auth Events</option>
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}" placeholder="From">
                            </div>
                            <div class="col-md-2 mb-2">
                                <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}" placeholder="To">
                            </div>
                            <div class="col-md-1 mb-2">
                                <button type="submit" class="btn btn-primary btn-block">Filter</button>
                            </div>
                        </div>
                    </form>
                    @if(isset($logs) && $logs->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="thead-light">
                                <tr>
                                    <th>Source</th>
                                    <th>User / Actor</th>
                                    <th>Action</th>
                                    <th>Affected Resource</th>
                                    <th>Status Change</th>
                                    <th>Amount</th>
                                    <th>IP Address</th>
                                    <th>Timestamp</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($logs as $log)
                                <tr>
                                    <td>
                                        @if($log['source'] === 'audit')
                                            <span class="badge badge-secondary">Model Audit</span>
                                        @else
                                            <span class="badge badge-primary">Activity</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($log['source'] === 'activity' && !empty($log['actor']))
                                            {{ $log['actor']->first_name ?? '' }} {{ $log['actor']->last_name ?? '' }}
                                            <small class="text-muted d-block">Actor</small>
                                        @elseif(!empty($log['user']))
                                            {{ $log['user']->first_name ?? '' }} {{ $log['user']->last_name ?? '' }}
                                        @else
                                            <span class="text-muted">System</span>
                                        @endif
                                    </td>
                                    <td><span class="badge badge-info">{{ $log['action'] ?? '-' }}</span></td>
                                    <td>
                                        @if($log['auditable_type'])
                                            {{ class_basename($log['auditable_type']) }} #{{ $log['auditable_id'] ?? '-' }}
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(!empty($log['previous_status']) || !empty($log['new_status']))
                                            <small>{{ $log['previous_status'] ?? '-' }} &rarr; {{ $log['new_status'] ?? '-' }}</small>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(!empty($log['amount']))
                                            N$ {{ number_format((float) $log['amount'], 2) }}
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>{{ $log['ip_address'] ?? '-' }}</td>
                                    <td>{{ $log['created_at']->format('M j, Y g:i A') }}</td>
                                    <td>
                                        <a href="{{ route('admin.audit.show', ['source' => $log['source'], 'id' => $log['id']]) }}" class="btn btn-sm btn-outline-info">
                                            <i class="mdi mdi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $logs->links() }}</div>
                    @else
                    <div class="text-center py-5">
                        <i class="mdi mdi-history text-muted" style="font-size:64px;"></i>
                        <h5 class="mt-3 text-muted">No Audit Logs</h5>
                        <p class="text-muted">No audit activity found.</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
