@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0"><i class="mdi mdi-shield-alert mr-2"></i>Fraud Alerts</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">Fraud Alerts</li>
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
                    <small class="text-muted">Total Alerts</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-danger mb-0">{{ formatKpi($stats['open']) }}</h4>
                    <small class="text-muted">Open</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-warning mb-0">{{ formatKpi($stats['investigating']) }}</h4>
                    <small class="text-muted">Investigating</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-danger mb-0">{{ formatKpi($stats['high_risk']) }}</h4>
                    <small class="text-muted">High Risk</small>
                </div>
            </div>
        </div>
    </div>
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-success mb-0">{{ formatKpi($stats['resolved']) }}</h4>
                    <small class="text-muted">Resolved Alerts</small>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Fraud Alerts</h5>
                    <form method="GET" class="form-inline mb-4">
                        <input type="text" name="search" class="form-control mr-2 mb-2" placeholder="Search by user, type, or description..." value="{{ request('search') }}">
                        <select name="status" class="form-control mr-2 mb-2">
                            <option value="">All Statuses</option>
                            <option value="open" {{ request('status')==='open'?'selected':'' }}>Open</option>
                            <option value="investigating" {{ request('status')==='investigating'?'selected':'' }}>Investigating</option>
                            <option value="resolved" {{ request('status')==='resolved'?'selected':'' }}>Resolved</option>
                        </select>
                        <select name="risk_level" class="form-control mr-2 mb-2">
                            <option value="">All Risk Levels</option>
                            <option value="low" {{ request('risk_level')==='low'?'selected':'' }}>Low</option>
                            <option value="medium" {{ request('risk_level')==='medium'?'selected':'' }}>Medium</option>
                            <option value="high" {{ request('risk_level')==='high'?'selected':'' }}>High</option>
                            <option value="critical" {{ request('risk_level')==='critical'?'selected':'' }}>Critical</option>
                        </select>
                        <button type="submit" class="btn btn-primary mb-2">Filter</button>
                    </form>
                    @if(isset($alerts) && $alerts->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="thead-light">
                                <tr><th>User</th><th>Type</th><th>Risk Level</th><th>Description</th><th>Status</th><th>Date</th><th>Action</th></tr>
                            </thead>
                            <tbody>
                                @foreach($alerts as $alert)
                                <tr>
                                    <td>{{ optional($alert->detector)->first_name }} {{ optional($alert->detector)->last_name }}</td>
                                    <td>{{ ucfirst(str_replace('_',' ',$alert->flag_type ?? '-')) }}</td>
                                    <td>
                                        @php $rl=['low'=>'success','medium'=>'warning','high'=>'danger','critical'=>'danger']; @endphp
                                        <span class="badge badge-{{ $rl[$alert->severity] ?? 'secondary' }}">{{ ucfirst($alert->severity ?? '-') }}</span>
                                    </td>
                                    <td>{{ Str::limit($alert->description ?? '-', 60) }}</td>
                                    <td>
                                        <span class="badge badge-{{ $alert->status === 'resolved' ? 'success' : ($alert->status === 'investigating' ? 'warning' : 'danger') }}">{{ ucfirst($alert->status ?? 'open') }}</span>
                                    </td>
                                    <td>{{ $alert->created_at->format('M j, Y') }}</td>
                                    <td><a href="{{ route('admin.fraud.show', $alert) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $alerts->links() }}</div>
                    @else
                    <div class="text-center py-5">
                        <i class="mdi mdi-shield-check text-success" style="font-size:64px;"></i>
                        <h5 class="mt-3 text-muted">No Fraud Alerts</h5>
                        <p class="text-muted">No suspicious activity has been detected.</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
