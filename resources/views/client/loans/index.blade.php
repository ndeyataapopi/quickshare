@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">My Loans</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">My Loans</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>
    @endif
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title text-uppercase mb-0">Loan Applications</h5>
                        <a href="{{ route('client.loans.create') }}" class="btn btn-primary btn-sm"><i class="mdi mdi-plus"></i> Apply for Loan</a>
                    </div>
                    @if($loans->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="thead-light">
                                <tr>
                                    <th>Reference</th>
                                    <th>Amount</th>
                                    <th>Purpose</th>
                                    <th>Term</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($loans as $loan)
                                <tr>
                                    <td>{{ $loan->reference }}</td>
                                    <td>{{ kpiMoney($loan->requested_amount) }}</td>
                                    <td>{{ $loan->purpose }}</td>
                                    <td>{{ $loan->loan_term_days }} days</td>
                                    <td>
                                        @php
                                            $b = ['pending_review'=>'warning','marketplace'=>'info','partially_funded'=>'info','funded'=>'primary','disbursed'=>'primary','active'=>'primary','completed'=>'success','rejected'=>'danger','cancelled'=>'secondary','defaulted'=>'danger','overdue'=>'warning'];
                                        @endphp
                                        <span class="badge badge-{{ $b[$loan->status] ?? 'secondary' }}">{{ ucwords(str_replace('_',' ',$loan->status)) }}</span>
                                    </td>
                                    <td>{{ $loan->created_at->format('M j, Y') }}</td>
                                    <td><a href="{{ route('client.loans.show', $loan) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $loans->links() }}</div>
                    @else
                    <div class="text-center py-5">
                        <i class="mdi mdi-cash text-muted" style="font-size:64px;"></i>
                        <h5 class="mt-3 text-muted">No Loan Applications Yet</h5>
                        <p class="text-muted">Apply for your first loan to get started.</p>
                        <a href="{{ route('client.loans.create') }}" class="btn btn-primary">Apply for a Loan</a>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
