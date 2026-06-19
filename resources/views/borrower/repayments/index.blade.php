@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <div class="row page-titles">
        <div class="col-md-5 align-self-center"><h4 class="text-themecolor">Repayments</h4></div>
        <div class="col-md-7 align-self-center text-right">
            <ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li><li class="breadcrumb-item active">Repayments</li></ol>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card"><div class="card-body">
                <h5 class="card-title text-muted text-uppercase small">Total Paid</h5>
                <h2 class="font-bold">N$ {{ number_format(auth()->user()->repayments()->where('status','paid')->sum('amount')) }}</h2>
                <i class="mdi mdi-check-circle text-success float-right" style="font-size:40px;opacity:.3"></i>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card"><div class="card-body">
                <h5 class="card-title text-muted text-uppercase small">Pending</h5>
                <h2 class="font-bold">{{ auth()->user()->repayments()->where('status','pending')->count() }}</h2>
                <i class="mdi mdi-clock text-warning float-right" style="font-size:40px;opacity:.3"></i>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card"><div class="card-body">
                <h5 class="card-title text-muted text-uppercase small">Overdue</h5>
                <h2 class="font-bold">{{ auth()->user()->repayments()->where('status','overdue')->count() }}</h2>
                <i class="mdi mdi-alert text-danger float-right" style="font-size:40px;opacity:.3"></i>
            </div></div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-3">Repayment History</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Due Date</th>
                                    <th>Loan</th>
                                    <th>Amount</th>
                                    <th>Principal</th>
                                    <th>Interest</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th>Paid Date</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($repayments ?? auth()->user()->repayments()->orderBy('due_date','desc')->paginate(20) as $r)
                            <tr>
                                <td>{{ $r->due_date }}</td>
                                <td><a href="{{ route('client.loans.show',$r->loan_id) }}">{{ $r->loan->reference ?? $r->loan_id }}</a></td>
                                <td>N$ {{ number_format($r->amount) }}</td>
                                <td>N$ {{ number_format($r->principal ?? 0) }}</td>
                                <td>N$ {{ number_format($r->interest ?? 0) }}</td>
                                <td>{{ ucfirst(str_replace('_',' ',$r->payment_method ?? '-')) }}</td>
                                <td><span class="badge badge-{{ $r->status==='paid' ? 'success' : ($r->status==='overdue' ? 'danger' : ($r->status==='partial' ? 'info' : 'warning')) }}">{{ ucfirst($r->status) }}</span></td>
                                <td>{{ $r->paid_date ?? '-' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No repayment records found.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if(isset($repayments) && $repayments instanceof \Illuminate\Pagination\LengthAwarePaginator)
                        <div class="mt-3">{{ $repayments->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
