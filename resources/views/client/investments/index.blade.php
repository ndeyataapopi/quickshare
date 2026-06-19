@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">My Investments</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">My Investments</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title text-uppercase mb-0">Investment Portfolio</h5>
                        <a href="{{ route('client.marketplace.index') }}" class="btn btn-primary btn-sm"><i class="mdi mdi-plus"></i> Browse Marketplace</a>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3 col-6">
                            <div class="p-2 bg-light rounded text-center">
                                <h5 class="mb-0 font-weight-bold text-primary">N$ {{ number_format($summary['total_invested'], 2) }}</h5>
                                <small class="text-muted">Total Invested</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="p-2 bg-light rounded text-center">
                                <h5 class="mb-0 font-weight-bold text-success">N$ {{ number_format($summary['total_expected'], 2) }}</h5>
                                <small class="text-muted">Expected Return</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="p-2 bg-light rounded text-center">
                                <h5 class="mb-0 font-weight-bold text-info">{{ $summary['active_count'] }}</h5>
                                <small class="text-muted">Active</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="p-2 bg-light rounded text-center">
                                <h5 class="mb-0 font-weight-bold text-secondary">{{ $summary['completed_count'] }}</h5>
                                <small class="text-muted">Completed</small>
                            </div>
                        </div>
                    </div>
                    @if($investments->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th><th>Loan</th><th>Invested</th><th>Rate</th><th>Expected Return</th><th>Status</th><th>Date</th><th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($investments as $investment)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $investment->loan ? $investment->loan->reference : '—' }}</td>
                                    <td>N$ {{ number_format($investment->amount, 2) }}</td>
                                    <td>{{ $investment->interest_rate ?? '-' }}%</td>
                                    <td class="text-success">N$ {{ number_format($investment->expected_return, 2) }}</td>
                                    <td>
                                        @php $sc=['pending'=>'warning','active'=>'primary','completed'=>'success','cancelled'=>'secondary']; @endphp
                                        <span class="badge badge-{{ $sc[$investment->status] ?? 'secondary' }}">{{ ucfirst($investment->status) }}</span>
                                    </td>
                                    <td>{{ $investment->created_at->format('M j, Y') }}</td>
                                    <td><a href="{{ route('client.investments.show', $investment) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $investments->links() }}</div>
                    @else
                    <div class="text-center py-5">
                        <i class="mdi mdi-trending-up text-muted" style="font-size:64px;"></i>
                        <h5 class="mt-3 text-muted">No Investments Yet</h5>
                        <p class="text-muted">Fund loans in the marketplace to start earning returns.</p>
                        <a href="{{ route('client.marketplace.index') }}" class="btn btn-primary">Browse Marketplace</a>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
