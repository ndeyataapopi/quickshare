@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">User Management</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">Users</li>
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
                    <h5 class="card-title text-uppercase mb-3">All Users</h5>
                    <form method="GET" class="form-inline mb-4">
                        <input type="text" name="search" class="form-control mr-2 mb-2" placeholder="Search users..." value="{{ request('search') }}">
                        <select name="role" class="form-control mr-2 mb-2">
                            <option value="">All Roles</option>
                            <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                            <option value="client" {{ request('role') === 'client' ? 'selected' : '' }}>Client</option>
                        </select>
                        <select name="status" class="form-control mr-2 mb-2">
                            <option value="">All Statuses</option>
                            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                        </select>
                        <button type="submit" class="btn btn-primary mb-2">Filter</button>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="thead-light">
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Trust Score</th>
                                    <th>KYC</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($users as $user)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mr-2" style="width:36px;height:36px;font-size:13px;font-weight:600;flex-shrink:0;">
                                                {{ strtoupper(substr($user->first_name,0,1)) }}{{ strtoupper(substr($user->last_name,0,1)) }}
                                            </div>
                                            <div>
                                                <div class="font-weight-bold">{{ $user->first_name }} {{ $user->last_name }}</div>
                                                <small class="text-muted">{{ $user->phone }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $user->email }}</td>
                                    <td>
                                        @foreach($user->roles as $role)
                                            <span class="badge badge-{{ $role->name === 'admin' ? 'danger' : 'primary' }}">{{ ucfirst($role->name) }}</span>
                                        @endforeach
                                    </td>
                                    <td>
                                        <span class="font-weight-bold">{{ number_format($user->trust_score ?? 0) }}</span>
                                        <span class="badge badge-warning ml-1">{{ ucfirst($user->trust_tier ?? 'bronze') }}</span>
                                    </td>
                                    <td>
                                        @php $kycStatus = $user->kycSubmission?->status ?? 'none'; $kycMap = ['pending'=>'warning','approved'=>'success','rejected'=>'danger','none'=>'secondary']; @endphp
                                        <span class="badge badge-{{ $kycMap[$kycStatus] ?? 'secondary' }}">{{ ucfirst($kycStatus) }}</span>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $user->status === 'active' ? 'success' : ($user->status === 'pending' ? 'warning' : 'danger') }}">{{ ucfirst($user->status) }}</span>
                                    </td>
                                    <td>{{ $user->created_at->format('M j, Y') }}</td>
                                    <td>
                                        @if($user->kycSubmission && $user->kycSubmission->status === 'pending')
                                            <a href="{{ route('admin.kyc.show', $user->kycSubmission) }}" class="btn btn-sm btn-warning">Review KYC</a>
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">No users found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">{{ $users->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
