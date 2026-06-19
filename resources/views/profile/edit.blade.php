@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">My Profile</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">My Profile</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    @if(session('status') === 'profile-updated')
        <div class="alert alert-success alert-dismissible fade show">Profile updated successfully.<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>
    @endif
    @if(session('status') === 'password-updated')
        <div class="alert alert-success alert-dismissible fade show">Password updated successfully.<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>
    @endif

    <div class="row">
        <div class="col-md-8">
            <!-- Update Profile Information -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-4">Profile Information</h5>
                    <form method="POST" action="{{ route('client.profile.update') }}">
                        @csrf @method('PATCH')
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name', $user->first_name) }}" required>
                                    @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name', $user->last_name) }}" required>
                                    @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $user->phone) }}">
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </form>
                </div>
            </div>

            <!-- Update Password -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-4">Update Password</h5>
                    <form method="POST" action="{{ route('password.update') }}">
                        @csrf @method('PUT')
                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" required>
                            @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="password_confirmation" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3" style="width:80px;height:80px;font-size:28px;font-weight:600;">
                        {{ strtoupper(substr($user->first_name,0,1)) }}{{ strtoupper(substr($user->last_name,0,1)) }}
                    </div>
                    <h5 class="mb-1">{{ $user->first_name }} {{ $user->last_name }}</h5>
                    <p class="text-muted small">{{ $user->email }}</p>
                    <span class="badge badge-primary">{{ ucfirst($user->roles->first()?->name ?? 'Client') }}</span>
                    <hr>
                    <div class="text-left">
                        <div class="d-flex justify-content-between mb-2">
                            <small class="text-muted">Trust Score</small>
                            <small class="font-weight-bold">{{ number_format($user->trust_score ?? 0) }}</small>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <small class="text-muted">Trust Tier</small>
                            <small class="font-weight-bold">{{ ucfirst($user->trust_tier ?? 'Bronze') }}</small>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <small class="text-muted">Member Since</small>
                            <small class="font-weight-bold">{{ $user->created_at->format('M Y') }}</small>
                        </div>
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">KYC Status</small>
                            @php $ks = $user->kycSubmission?->status ?? 'none'; $km=['approved'=>'success','pending'=>'warning','rejected'=>'danger','none'=>'secondary']; @endphp
                            <span class="badge badge-{{ $km[$ks] }}">{{ ucfirst($ks) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
