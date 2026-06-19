@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">Fraud Alert Details</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.fraud.index') }}">Fraud Alerts</a></li>
                    <li class="breadcrumb-item active">Details</li>
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
                    <h5 class="card-title text-uppercase mb-4">Alert Information</h5>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">User</div><div class="col-sm-8">{{ optional($alert->user)->first_name }} {{ optional($alert->user)->last_name }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Alert Type</div><div class="col-sm-8">{{ ucfirst(str_replace('_',' ',$alert->type ?? '-')) }}</div></div>
                    <div class="row mb-3">
                        <div class="col-sm-4 text-muted">Risk Level</div>
                        <div class="col-sm-8">
                            @php $rl=['low'=>'success','medium'=>'warning','high'=>'danger','critical'=>'danger']; @endphp
                            <span class="badge badge-{{ $rl[$alert->risk_level] ?? 'secondary' }}">{{ ucfirst($alert->risk_level ?? '-') }}</span>
                        </div>
                    </div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Description</div><div class="col-sm-8">{{ $alert->description ?? '-' }}</div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Status</div><div class="col-sm-8"><span class="badge badge-{{ $alert->status === 'resolved' ? 'success' : 'warning' }}">{{ ucfirst($alert->status ?? 'open') }}</span></div></div>
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Detected On</div><div class="col-sm-8">{{ $alert->created_at->format('M j, Y g:i A') }}</div></div>
                    @if($alert->metadata)
                    <div class="row mb-3"><div class="col-sm-4 text-muted">Metadata</div><div class="col-sm-8"><pre class="small bg-light p-2 rounded">{{ json_encode($alert->metadata, JSON_PRETTY_PRINT) }}</pre></div></div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Actions</h5>
                    @if($alert->status !== 'resolved')
                    <form method="POST" action="{{ route('admin.fraud.update', $alert) }}" class="mb-2">
                        @csrf @method('PUT')
                        <input type="hidden" name="status" value="resolved">
                        <button type="submit" class="btn btn-success btn-block" onclick="return confirm('Mark as resolved?')">Mark Resolved</button>
                    </form>
                    @endif
                    <a href="{{ route('admin.fraud.index') }}" class="btn btn-outline-secondary btn-block mt-2"><i class="mdi mdi-arrow-left"></i> Back to Alerts</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
