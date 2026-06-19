@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">Repayments</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">Repayments</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>
    @endif
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Payment History</h5>
                    @if($repayments->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th><th>Loan</th><th>Amount</th><th>Due Date</th><th>Status</th><th>Paid On</th><th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($repayments as $repayment)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $repayment->loan ? $repayment->loan->reference : '#' . $repayment->loan_id }}</td>
                                    <td>{{ kpiMoney($repayment->amount) }}</td>
                                    <td>{{ optional($repayment->due_date)->format('M j, Y') ?? '-' }}</td>
                                    <td>
                                        @php $sc=['paid'=>'success','overdue'=>'danger','pending'=>'warning','defaulted'=>'danger']; @endphp
                                        <span class="badge badge-{{ $sc[$repayment->status] ?? 'secondary' }}">
                                            {{ ucfirst($repayment->status) }}
                                        </span>
                                    </td>
                                    <td>{{ optional($repayment->paid_at)->format('M j, Y') ?? '-' }}</td>
                                    <td><a href="{{ route('client.repayments.show', $repayment) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $repayments->links() }}</div>
                    @else
                    <div class="text-center py-5">
                        <i class="mdi mdi-cash-usd text-muted" style="font-size:64px;"></i>
                        <h5 class="mt-3 text-muted">No Repayments Yet</h5>
                        <p class="text-muted">Your loan repayments will appear here.</p>
                        <a href="{{ route('client.loans.index') }}" class="btn btn-primary">View My Loans</a>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
