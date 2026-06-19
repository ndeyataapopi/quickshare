@extends('layouts.app')
@section('content')
<div class="container-fluid">
    <div class="row page-titles">
        <div class="col-md-5 align-self-center"><h4 class="text-themecolor">My Loans</h4></div>
        <div class="col-md-7 align-self-center text-right">
            <ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li><li class="breadcrumb-item active">My Loans</li></ol>
        </div>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">All Loans</h5>
                        <a href="{{ route('client.loans.create') }}" class="btn btn-primary btn-sm"><i class="mdi mdi-plus mr-1"></i>New Loan</a>
                    </div>
                    <form class="d-flex gap-2 mb-3" method="GET">
                        <select name="status" class="form-control form-control-sm" style="width:auto" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            @foreach(['pending_review','marketplace','active','completed','defaulted','cancelled'] as $s)
                            <option value="{{ $s }}" {{ request('status')===$s ? 'selected' : '' }}>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
                            @endforeach
                        </select>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Reference</th>
                                    <th>Amount</th>
                                    <th>Interest</th>
                                    <th>Term</th>
                                    <th>Status</th>
                                    <th>Repayment Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($loans ?? [] as $loan)
                            <tr>
                                <td><strong>{{ $loan->reference }}</strong></td>
                                <td>N$ {{ number_format($loan->requested_amount) }}</td>
                                <td>{{ $loan->interest_rate }}%</td>
                                <td>{{ $loan->loan_term_days }} days</td>
                                <td>
                                    @php $sc=['active'=>'success','completed'=>'primary','pending_review'=>'warning','defaulted'=>'danger','cancelled'=>'secondary','marketplace'=>'info']; @endphp
                                    <span class="badge badge-{{ $sc[$loan->status] ?? 'secondary' }}">{{ ucfirst(str_replace('_',' ',$loan->status)) }}</span>
                                </td>
                                <td>{{ $loan->repayment_date ?? '-' }}</td>
                                <td><a href="{{ route('client.loans.show',$loan) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                            </tr>
                            @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No loans found. <a href="{{ route('client.loans.create') }}">Apply for your first loan</a>.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if(isset($loans) && $loans instanceof \Illuminate\Pagination\LengthAwarePaginator)
                        <div class="mt-3">{{ $loans->withQueryString()->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
