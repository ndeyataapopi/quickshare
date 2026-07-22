@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">System Status</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">System Status</li>
                </ol>
            </nav>
        </div>
    </div>
</div>

<div class="page-content container-fluid">
    @if (session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session()->get('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session()->get('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
    @endif

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase">Queue Connection</h5>
                    <h3 class="mb-0">{{ $queue_connection }}</h3>
                    <small class="text-muted">Driver: {{ $queue_driver }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase">Worker Status</h5>
                    <h3 class="mb-0 {{ $worker_running ? 'text-success' : 'text-danger' }}">
                        {{ $worker_running ? 'Running' : 'Stopped' }}
                    </h3>
                    @if ($worker_pid)
                        <small class="text-muted">PID: {{ $worker_pid }} | Uptime: {{ $worker_uptime ?? 'Unknown' }}</small>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase">Failed Jobs</h5>
                    <h3 class="mb-0 {{ $failed_jobs > 0 ? 'text-danger' : 'text-success' }}">{{ $failed_jobs }}</h3>
                    @if ($last_failed_job)
                        <small class="text-muted">Last failure: {{ \Carbon\Carbon::parse($last_failed_job->failed_at)->diffForHumans() }}</small>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="card-title text-uppercase mb-0">Pending Jobs</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr><th>Queue</th><th class="text-right">Pending</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($queues as $queue => $size)
                                <tr>
                                    <td>{{ $queue }}</td>
                                    <td class="text-right font-weight-bold">{{ is_numeric($size) ? number_format($size) : $size }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="card-title text-uppercase mb-0">Last Worker Restart</h5>
                </div>
                <div class="card-body">
                    <p class="mb-0">
                        @if ($last_restart)
                            {{ \Carbon\Carbon::createFromTimestamp($last_restart)->diffForHumans() }}
                        @else
                            No restart signal recorded.
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="card-title text-uppercase mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.system-status.restart-worker') }}" class="d-inline mr-2">
                        @csrf
                        <button type="submit" class="btn btn-warning">Restart Worker</button>
                    </form>

                    <form method="POST" action="{{ route('admin.system-status.retry-failed') }}" class="d-inline mr-2">
                        @csrf
                        <button type="submit" class="btn btn-info" {{ $failed_jobs == 0 ? 'disabled' : '' }}>Retry All Failed</button>
                    </form>

                    <form method="POST" action="{{ route('admin.system-status.clear-failed') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-danger" {{ $failed_jobs == 0 ? 'disabled' : '' }}>Clear Failed Jobs</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
