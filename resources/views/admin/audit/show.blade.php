@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0"><i class="mdi mdi-history mr-2"></i>Audit Log Detail</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.audit.index') }}">Audit Logs</a></li>
                    <li class="breadcrumb-item active">{{ ucfirst($data['source']) }} #{{ $data['id'] }}</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-4">
                        {{ ucfirst($data['source']) }} Log #{{ $data['id'] }}
                        @if($data['source'] === 'audit')
                            <span class="badge badge-secondary ml-2">Model Audit</span>
                        @else
                            <span class="badge badge-primary ml-2">Activity Log</span>
                        @endif
                    </h5>

                    <div class="row mb-3"><div class="col-sm-4 text-muted">Action</div><div class="col-sm-8"><span class="badge badge-info">{{ $data['action'] }}</span></div></div>

                    @if(!empty($data['description']))
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Description</div><div class="col-sm-8">{{ $data['description'] }}</div></div>
                    @endif

                    <div class="row mb-3"><div class="col-sm-4 text-muted">Affected Resource</div><div class="col-sm-8">
                        @if($data['auditable_type'])
                            {{ class_basename($data['auditable_type']) }} #{{ $data['auditable_id'] ?? '-' }}
                        @else
                            <span class="text-muted">N/A</span>
                        @endif
                    </div></div>

                    @if(!empty($data['previous_status']) || !empty($data['new_status']))
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Status Change</div><div class="col-sm-8">
                        <span class="badge badge-warning">{{ $data['previous_status'] ?? '-' }}</span>
                        &rarr;
                        <span class="badge badge-success">{{ $data['new_status'] ?? '-' }}</span>
                    </div></div>
                    @endif

                    @if(!empty($data['amount']))
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Amount</div><div class="col-sm-8 font-weight-bold">N$ {{ number_format((float) $data['amount'], 2) }}</div></div>
                    @endif

                    <div class="row mb-3"><div class="col-sm-4 text-muted">Timestamp</div><div class="col-sm-8">{{ $data['created_at']->format('M j, Y g:i A') }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">IP Address</div><div class="col-sm-8">{{ $data['ip_address'] ?? '-' }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">User Agent</div><div class="col-sm-8"><small>{{ $data['user_agent'] ?? '-' }}</small></div></div>
                </div>
            </div>

            @if(!empty($data['old_values']) || !empty($data['new_values']))
            <div class="card mt-3">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Value Changes</h5>
                    <div class="row">
                        @if(!empty($data['old_values']))
                        <div class="col-md-6">
                            <h6 class="text-danger mb-2">Old Values</h6>
                            <pre class="bg-light p-3 rounded"><code>{{ json_encode($data['old_values'], JSON_PRETTY_PRINT) }}</code></pre>
                        </div>
                        @endif
                        @if(!empty($data['new_values']))
                        <div class="col-md-6">
                            <h6 class="text-success mb-2">New Values</h6>
                            <pre class="bg-light p-3 rounded"><code>{{ json_encode($data['new_values'], JSON_PRETTY_PRINT) }}</code></pre>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            @if(!empty($data['metadata']))
            <div class="card mt-3">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Metadata</h5>
                    <pre class="bg-light p-3 rounded"><code>{{ json_encode($data['metadata'], JSON_PRETTY_PRINT) }}</code></pre>
                </div>
            </div>
            @endif
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Actor Information</h5>
                    @if(!empty($data['actor']))
                        <div class="row mb-3"><div class="col-sm-4 text-muted">Actor</div><div class="col-sm-8 font-weight-bold">{{ $data['actor']->first_name }} {{ $data['actor']->last_name }}</div></div>
                        <div class="row mb-3"><div class="col-sm-4 text-muted">Email</div><div class="col-sm-8">{{ $data['actor']->email }}</div></div>
                    @endif
                    @if(!empty($data['user']))
                        <div class="row mb-3"><div class="col-sm-4 text-muted">User</div><div class="col-sm-8 font-weight-bold">{{ $data['user']->first_name }} {{ $data['user']->last_name }}</div></div>
                        <div class="row mb-3"><div class="col-sm-4 text-muted">Email</div><div class="col-sm-8">{{ $data['user']->email }}</div></div>
                    @endif
                    @if(empty($data['user']) && empty($data['actor']))
                        <p class="text-muted">System-generated event</p>
                    @endif
                </div>
            </div>
            <a href="{{ route('admin.audit.index') }}" class="btn btn-outline-secondary btn-block mt-3">
                <i class="mdi mdi-arrow-left mr-1"></i> Back to Audit Logs
            </a>
        </div>
    </div>
</div>
@endsection
