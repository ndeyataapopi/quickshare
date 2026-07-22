@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <div class="row page-titles">
        <div class="col-md-5 align-self-center"><h4 class="text-themecolor">Dashboard</h4></div>
        <div class="col-md-7 align-self-center text-right">
            <ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li><li class="breadcrumb-item active">Dashboard</li></ol>
        </div>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <div class="row">
        <div class="col-md-3">
            <div class="card"><div class="card-body">
                <h5 class="card-title text-muted text-uppercase small">Trust Score</h5>
                <h2 class="font-bold">{{ $user->trust_score ?? 0 }}<small class="text-muted">/100</small></h2>
                <i class="mdi mdi-shield-check text-success float-right" style="font-size:40px;opacity:.3"></i>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card"><div class="card-body">
                <h5 class="card-title text-muted text-uppercase small">Active Loans</h5>
                <h2 class="font-bold">{{ $loans->where('status','active')->count() }}</h2>
                <i class="mdi mdi-cash text-info float-right" style="font-size:40px;opacity:.3"></i>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card"><div class="card-body">
                <h5 class="card-title text-muted text-uppercase small">Outstanding Balance</h5>
                <h2 class="font-bold">N$ {{ number_format($totalOutstanding, 2) }}</h2>
                <i class="mdi mdi-alert-circle-outline text-danger float-right" style="font-size:40px;opacity:.3"></i>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card"><div class="card-body">
                <h5 class="card-title text-muted text-uppercase small">Total Repaid</h5>
                <h2 class="font-bold">{{ formatCurrencyShort($repayments->where('status','paid')->sum('amount')) }}</h2>
                <i class="mdi mdi-check-circle text-success float-right" style="font-size:40px;opacity:.3"></i>
            </div></div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-4">
            <a href="{{ route('client.loans.create') }}" class="card text-center p-4 h-100 text-decoration-none">
                <div class="card-body"><i class="mdi mdi-plus-circle text-primary" style="font-size:36px"></i><h5 class="mt-2">Request Loan</h5><p class="text-muted small">Apply for a new loan</p></div>
            </a>
        </div>
        <div class="col-md-4 mb-4">
            <a href="{{ route('client.kyc.upload') }}" class="card text-center p-4 h-100 text-decoration-none">
                <div class="card-body"><i class="mdi mdi-upload text-info" style="font-size:36px"></i><h5 class="mt-2">KYC Documents</h5><p class="text-muted small">Complete verification</p></div>
            </a>
        </div>
        <div class="col-md-4 mb-4">
            <a href="{{ route('client.referrals.index') }}" class="card text-center p-4 h-100 text-decoration-none">
                <div class="card-body"><i class="mdi mdi-account-group text-success" style="font-size:36px"></i><h5 class="mt-2">Refer Friends</h5><p class="text-muted small">Earn referral rewards</p></div>
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Recent Loans</h5>
                    @if($loans->count())
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Reference</th><th>Amount</th><th>Status</th></tr></thead>
                            <tbody>
                            @foreach($loans as $loan)
                            <tr>
                                <td><a href="{{ route('client.loans.show',$loan) }}">{{ $loan->reference }}</a></td>
                                <td>N$ {{ number_format($loan->requested_amount) }}</td>
                                <td><span class="badge badge-{{ in_array($loan->status,['active','completed']) ? 'success' : ($loan->status==='pending_review' ? 'warning' : 'secondary') }}">{{ ucfirst(str_replace('_',' ',$loan->status)) }}</span></td>
                            </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else<p class="text-muted">No loans yet. <a href="{{ route('client.loans.create') }}">Apply now</a></p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Recent Repayments</h5>
                    @if($repayments->count())
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Amount</th><th>Status</th><th>Due</th></tr></thead>
                            <tbody>
                            @foreach($repayments as $r)
                            <tr>
                                <td>N$ {{ number_format($r->amount) }}</td>
                                <td><span class="badge badge-{{ $r->status==='paid' ? 'success' : ($r->status==='overdue' ? 'danger' : 'warning') }}">{{ ucfirst($r->status) }}</span></td>
                                <td>{{ $r->due_date }}</td>
                            </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else<p class="text-muted">No repayments yet.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Repayment Schedule Overview</h5>
                    @if(!empty($repaymentChartLabels))
                        <div id="repaymentChart" style="height: 300px;"></div>
                    @else
                        <p class="text-muted text-center py-5">No repayment data to chart yet.</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Upcoming Repayments (Next 30 Days)</h5>
                    @if($upcomingRepayments->count())
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Loan</th><th>Amount</th><th>Due Date</th><th>Status</th></tr></thead>
                            <tbody>
                            @foreach($upcomingRepayments as $r)
                            <tr>
                                <td><a href="{{ route('client.loans.show', $r->loan) }}">{{ $r->loan->reference }}</a></td>
                                <td>N$ {{ number_format($r->amount) }}</td>
                                <td>{{ $r->due_date->toDateString() }}</td>
                                <td><span class="badge badge-warning">{{ ucfirst($r->status) }}</span></td>
                            </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else<p class="text-muted">No upcoming repayments in the next 30 days.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Trust Score</h5>
                    <div class="d-flex align-items-center">
                        <div class="mr-3"><span class="display-4 font-bold text-primary">{{ $user->trust_score ?? 0 }}</span><span class="text-muted">/100</span></div>
                        <div class="flex-grow-1">
                            <div class="progress" style="height:12px">
                                <div class="progress-bar bg-primary" style="width:{{ $user->trust_score ?? 0 }}%"></div>
                            </div>
                            <small class="text-muted mt-1 d-block">Build your score to access better rates</small>
                        </div>
                        <div class="ml-3"><a href="{{ route('client.trust-score.index') }}" class="btn btn-sm btn-outline-primary">View Details</a></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if(!empty($repaymentChartLabels))
<script>
$(function() {
    var chartLabels = @json($repaymentChartLabels);
    var chartData = @json($repaymentChartData);

    new Chartist.Bar('#repaymentChart', {
        labels: chartLabels,
        series: [chartData]
    }, {
        axisX: {
            showGrid: false
        },
        axisY: {
            labelInterpolationFnc: function(value) {
                return 'N$ ' + Number(value).toLocaleString();
            }
        },
        plugins: [
            Chartist.plugins.tooltip({
                currencyFormatCallback: function(value) {
                    return 'N$ ' + Number(value).toLocaleString();
                }
            })
        ]
    });
});
</script>
@endif
@endsection
