@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">User Details</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">Users</a></li>
                    <li class="breadcrumb-item active">{{ $user->first_name }} {{ $user->last_name }}</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Profile</h5>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Full Name</div><div class="col-sm-8 font-weight-bold">{{ $user->first_name }} {{ $user->last_name }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Email</div><div class="col-sm-8">{{ $user->email }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Phone</div><div class="col-sm-8">{{ $user->phone ?? '—' }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Date of Birth</div><div class="col-sm-8">{{ $user->date_of_birth ? $user->date_of_birth->format('M j, Y') : '—' }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Trust Score</div><div class="col-sm-8">{{ $user->trust_score }} <span class="badge badge-info">{{ ucfirst($user->trust_tier) }}</span></div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Roles</div><div class="col-sm-8">{{ $user->getRoleNames()->implode(', ') }}</div></div>
                    <div class="row mb-2">
                        <div class="col-sm-4 text-muted">Status</div>
                        <div class="col-sm-8">
                            @php $sc=['active'=>'success','pending'=>'warning','suspended'=>'danger']; @endphp
                            <span class="badge badge-{{ $sc[$user->status] ?? 'secondary' }}">{{ ucfirst($user->status) }}</span>
                        </div>
                    </div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Email Verified</div><div class="col-sm-8">{{ $user->email_verified_at ? $user->email_verified_at->format('M j, Y') : '<span class="text-danger">Not verified</span>' }}</div></div>
                    <div class="row mb-2"><div class="col-sm-4 text-muted">Joined</div><div class="col-sm-8">{{ $user->created_at->format('M j, Y') }}</div></div>
                    @if($user->address)
                    <hr>
                    <h6 class="text-uppercase font-weight-bold mb-2">Address</h6>
                    <p class="mb-0">{{ $user->address->house_number }} {{ $user->address->street }}, {{ $user->address->suburb }}, {{ $user->address->city }}, {{ $user->address->country }}</p>
                    @endif
                    @if($user->sourceOfIncome)
                    <hr>
                    <h6 class="text-uppercase font-weight-bold mb-2">Source of Income</h6>
                    <p class="mb-0">{{ ucfirst($user->sourceOfIncome->profession) }}{{ $user->sourceOfIncome->company_name ? ' — ' . $user->sourceOfIncome->company_name : '' }}</p>
                    @endif
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="text-uppercase font-weight-bold mb-3">KYC Status</h6>
                            @if($user->kycSubmission)
                                @php $sc=['pending'=>'warning','approved'=>'success','rejected'=>'danger','resubmission_required'=>'warning']; @endphp
                                <span class="badge badge-{{ $sc[$user->kycSubmission->status] ?? 'secondary' }}">
                                    {{ ucwords(str_replace('_', ' ', $user->kycSubmission->status)) }}
                                </span>
                                <br><small class="text-muted mt-1 d-block">Submitted: {{ $user->kycSubmission->submitted_at ? $user->kycSubmission->submitted_at->format('M j, Y') : '—' }}</small>
                                <a href="{{ route('admin.kyc.show', $user->kycSubmission) }}" class="btn btn-sm btn-outline-primary mt-2">View KYC</a>
                            @else
                                <span class="text-muted">No KYC submitted</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="text-uppercase font-weight-bold mb-3">Loan Summary</h6>
                            <p class="mb-1">Total Loans: <strong>{{ $user->loans->count() }}</strong></p>
                            <p class="mb-1">Active: <strong>{{ $user->loans->whereIn('status', ['active','disbursed'])->count() }}</strong></p>
                            <p class="mb-0">Completed: <strong>{{ $user->loans->where('status','completed')->count() }}</strong></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Update Status</h5>
                    @if(!$user->kycSubmission || $user->kycSubmission->status !== 'approved')
                    <div class="alert alert-info p-2 mb-3">
                        <small><i class="mdi mdi-information mr-1"></i> Setting status to <strong>Active</strong> will allow this user to bypass KYC requirements.</small>
                    </div>
                    @endif
                    <form method="POST" action="{{ route('admin.users.status', $user) }}">
                        @csrf @method('PATCH')
                        <div class="form-group">
                            <select name="status" class="form-control">
                                <option value="active" {{ $user->status === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="suspended" {{ $user->status === 'suspended' ? 'selected' : '' }}>Suspended</option>
                                <option value="pending" {{ $user->status === 'pending' ? 'selected' : '' }}>Pending</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block"
                            onclick="return confirm('Update user status?')">
                            Update Status
                        </button>
                    </form>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-block mt-2">
                        <i class="mdi mdi-arrow-left mr-1"></i> Back to Users
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
