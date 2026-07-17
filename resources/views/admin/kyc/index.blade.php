@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">KYC Reviews</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">KYC Reviews</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    <div class="row mb-4">
        <div class="col-md-3"><div class="card"><div class="card-body"><h6 class="text-uppercase text-muted">Pending</h6><h3 class="text-warning">{{ App\Modules\KYC\Models\KycSubmission::where('status','pending')->count() }}</h3></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><h6 class="text-uppercase text-muted">Approved Today</h6><h3 class="text-success">{{ App\Modules\KYC\Models\KycSubmission::where('status','approved')->whereDate('updated_at',today())->count() }}</h3></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><h6 class="text-uppercase text-muted">Rejected Today</h6><h3 class="text-danger">{{ App\Modules\KYC\Models\KycSubmission::where('status','rejected')->whereDate('updated_at',today())->count() }}</h3></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><h6 class="text-uppercase text-muted">Total Processed</h6><h3 class="text-primary">{{ App\Modules\KYC\Models\KycSubmission::whereIn('status',['approved','rejected'])->count() }}</h3></div></div></div>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">KYC Submissions</h5>
                    <form method="GET" class="form-inline mb-4">
                        <input type="text" name="search" class="form-control mr-2 mb-2" placeholder="Search by name or email..." value="{{ request('search') }}">
                        <select name="status" class="form-control mr-2 mb-2">
                            <option value="">All Statuses</option>
                            <option value="pending" {{ request('status')==='pending'?'selected':'' }}>Pending</option>
                            <option value="approved" {{ request('status')==='approved'?'selected':'' }}>Approved</option>
                            <option value="rejected" {{ request('status')==='rejected'?'selected':'' }}>Rejected</option>
                            <option value="resubmission_required" {{ request('status')==='resubmission_required'?'selected':'' }}>Resubmission Required</option>
                        </select>
                        <button type="submit" class="btn btn-primary mb-2">Filter</button>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="thead-light">
                                <tr><th>User</th><th>Document Type</th><th>Document #</th><th>Submitted</th><th>Status</th><th>Action</th></tr>
                            </thead>
                            <tbody>
                                @forelse($submissions as $submission)
                                <tr>
                                    <td>
                                        <div class="font-weight-bold">{{ $submission->user->first_name }} {{ $submission->user->last_name }}</div>
                                        <small class="text-muted">{{ $submission->user->email }}</small>
                                    </td>
                                    <td>{{ ucfirst(str_replace('_',' ',$submission->metadata['document_type'] ?? 'N/A')) }}</td>
                                    <td>{{ $submission->metadata['document_number'] ?? 'N/A' }}</td>
                                    <td>{{ optional($submission->submitted_at)->format('M j, Y g:i A') ?? $submission->created_at->format('M j, Y') }}</td>
                                    <td>
                                        @php $sc=['pending'=>'warning','approved'=>'success','rejected'=>'danger','resubmission_required'=>'info']; @endphp
                                        <span class="badge badge-{{ $sc[$submission->status] ?? 'secondary' }}">{{ ucwords(str_replace('_',' ',$submission->status)) }}</span>
                                    </td>
                                    <td><a href="{{ route('admin.kyc.show', $submission) }}" class="btn btn-sm btn-outline-primary">Review</a></td>
                                </tr>
                                @empty
                                <tr><td colspan="6" class="text-center py-4 text-muted">No KYC submissions found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $submissions->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
