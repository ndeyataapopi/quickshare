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
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">Repayments</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-warning mb-0">{{ $stats['pending'] }}</h4>
                    <small class="text-muted">Pending</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-success mb-0">{{ $stats['completed'] }}</h4>
                    <small class="text-muted">Completed</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body py-3">
                    <h4 class="font-weight-bold text-danger mb-0">{{ $stats['overdue'] }}</h4>
                    <small class="text-muted">Overdue</small>
                </div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <h5 class="card-title text-uppercase mb-3">All Repayments</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Loan Ref</th>
                            <th>Borrower</th>
                            <th>Amount</th>
                            <th>Due Date</th>
                            <th>Paid At</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($repayments as $r)
                        <tr>
                            <td>
                                @if($r->loan)
                                    <a href="{{ route('admin.loans.show', $r->loan) }}">{{ $r->loan->reference }}</a>
                                @else —
                                @endif
                            </td>
                            <td>{{ $r->loan && $r->loan->borrower ? $r->loan->borrower->first_name . ' ' . $r->loan->borrower->last_name : '—' }}</td>
                            <td>N$ {{ number_format($r->amount, 2) }}</td>
                            <td>{{ $r->due_date ? \Carbon\Carbon::parse($r->due_date)->format('M j, Y') : '—' }}</td>
                            <td>{{ $r->paid_at ? \Carbon\Carbon::parse($r->paid_at)->format('M j, Y') : '—' }}</td>
                            <td>
                                @php $sc=['pending'=>'warning','completed'=>'success','overdue'=>'danger']; @endphp
                                <span class="badge badge-{{ $sc[$r->status] ?? 'secondary' }}">{{ ucfirst($r->status) }}</span>
                            </td>
                            <td>
                                <a href="{{ route('admin.repayments.show', $r) }}" class="btn btn-xs btn-outline-primary">View</a>
                                @if($r->status === 'pending')
                                <form method="POST" action="{{ route('admin.repayments.confirm', $r) }}" class="d-inline">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-xs btn-success" onclick="return confirm('Confirm this repayment?')">Confirm</button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No repayments found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $repayments->links() }}
        </div>
    </div>
</div>
@endsection
