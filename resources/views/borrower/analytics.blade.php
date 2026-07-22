@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <div class="row page-titles">
        <div class="col-md-5 align-self-center"><h4 class="text-themecolor">Analytics</h4></div>
        <div class="col-md-7 align-self-center text-right">
            <ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li><li class="breadcrumb-item active">Analytics</li></ol>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3">
            <div class="card"><div class="card-body">
                <h5 class="card-title text-muted text-uppercase small">Total Borrowed</h5>
                <h2 class="font-bold">N$ {{ number_format($totalBorrowed ?? 0) }}</h2>
                <i class="mdi mdi-cash-multiple text-primary float-right" style="font-size:40px;opacity:.3"></i>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card"><div class="card-body">
                <h5 class="card-title text-muted text-uppercase small">Total Repaid</h5>
                <h2 class="font-bold">N$ {{ number_format($totalRepaid ?? 0) }}</h2>
                <i class="mdi mdi-check-all text-success float-right" style="font-size:40px;opacity:.3"></i>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card"><div class="card-body">
                <h5 class="card-title text-muted text-uppercase small">Active Loans</h5>
                <h2 class="font-bold">{{ $activeLoansCount ?? 0 }}</h2>
                <i class="mdi mdi-bank text-info float-right" style="font-size:40px;opacity:.3"></i>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card"><div class="card-body">
                <h5 class="card-title text-muted text-uppercase small">Overdue Repayments</h5>
                <h2 class="font-bold">{{ $overdueRepayments ?? 0 }}</h2>
                <i class="mdi mdi-alert text-danger float-right" style="font-size:40px;opacity:.3"></i>
            </div></div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Loans by Status</h5>
                    @php
                        $statusColors = ['active'=>'success','completed'=>'primary','pending_review'=>'warning','defaulted'=>'danger','cancelled'=>'secondary','marketplace'=>'info'];
                    @endphp
                    @foreach($loansByStatus as $status => $count)
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge badge-{{ $statusColors[$status] ?? 'secondary' }}">{{ ucfirst(str_replace('_',' ',$status)) }}</span>
                        <div class="flex-grow-1 mx-3">
                            <div class="progress" style="height:8px">
                                <div class="progress-bar bg-{{ $statusColors[$status] ?? 'secondary' }}" style="width:{{ $loansByStatus->sum() > 0 ? round($count/$loansByStatus->sum()*100) : 0 }}%"></div>
                            </div>
                        </div>
                        <span class="font-weight-bold">{{ $count }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Repayments by Status</h5>
                    @php
                        $repColors = ['paid'=>'success','pending'=>'warning','overdue'=>'danger','partial'=>'info'];
                    @endphp
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Status</th><th>Count</th><th>Total Amount</th></tr></thead>
                            <tbody>
                            @foreach($repByStatus as $r)
                            <tr>
                                <td><span class="badge badge-{{ $repColors[$r->status] ?? 'secondary' }}">{{ ucfirst($r->status) }}</span></td>
                                <td>{{ $r->total }}</td>
                                <td>N$ {{ number_format($r->total_amount) }}</td>
                            </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Trust Score Progress</h5>
                    <div class="d-flex align-items-center">
                        <span class="display-4 font-bold text-primary mr-3">{{ $score ?? 0 }}</span>
                        <div class="flex-grow-1">
                            <div class="progress" style="height:14px">
                                <div class="progress-bar bg-primary" style="width:{{ $score ?? 0 }}%"></div>
                            </div>
                            <small class="text-muted mt-1 d-block">Higher scores unlock better interest rates</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
