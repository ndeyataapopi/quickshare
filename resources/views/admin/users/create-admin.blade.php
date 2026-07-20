@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">Create Admin User</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">Users</a></li>
                    <li class="breadcrumb-item active">Create Admin User</li>
                </ol>
            </nav>
        </div>
    </div>
</div>

<div class="page-content container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-4">Admin Staff Information</h5>

                    <form method="POST" action="{{ route('admin.users.store-admin') }}">
                        @csrf

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="first_name">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('first_name') is-invalid @enderror"
                                           id="first_name" name="first_name" value="{{ old('first_name') }}" required>
                                    @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="last_name">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('last_name') is-invalid @enderror"
                                           id="last_name" name="last_name" value="{{ old('last_name') }}" required>
                                    @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control @error('email') is-invalid @enderror"
                                           id="email" name="email" value="{{ old('email') }}" required>
                                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="phone">Phone <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('phone') is-invalid @enderror"
                                           id="phone" name="phone" value="{{ old('phone') }}" required>
                                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="national_id">National ID <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('national_id') is-invalid @enderror"
                                           id="national_id" name="national_id" value="{{ old('national_id') }}" required>
                                    <small class="form-text text-muted">11 digits. First 6 digits must match date of birth (YYMMDD).</small>
                                    @error('national_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="date_of_birth">Date of Birth <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control @error('date_of_birth') is-invalid @enderror"
                                           id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth') }}" required>
                                    @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="password">Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control @error('password') is-invalid @enderror"
                                           id="password" name="password" required>
                                    <small class="form-text text-muted">Minimum 8 characters</small>
                                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="status">Status</label>
                                    <select class="form-control @error('status') is-invalid @enderror" id="status" name="status">
                                        <option value="pending" {{ old('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>Active</option>
                                        <option value="suspended" {{ old('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                                    </select>
                                    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h6 class="card-subtitle mb-3 text-muted">Roles</h6>
                        <div class="form-group">
                            @foreach($roles as $role)
                                <div class="custom-control custom-checkbox mb-2">
                                    <input type="checkbox" name="roles[]" value="{{ $role->name }}" id="role_{{ $role->id }}" class="custom-control-input" {{ in_array($role->name, old('roles', [])) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="role_{{ $role->id }}">
                                        <span class="badge badge-info">{{ ucwords(str_replace('_', ' ', $role->name)) }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>

                        <hr class="my-4">

                        <h6 class="card-subtitle mb-3 text-muted">Direct Permissions</h6>
                        <div class="form-group" style="max-height: 300px; overflow-y: auto;">
                            @foreach($permissions as $permission)
                                <div class="custom-control custom-checkbox mb-2">
                                    <input type="checkbox" name="permissions[]" value="{{ $permission->name }}" id="permission_{{ $permission->id }}" class="custom-control-input" {{ in_array($permission->name, old('permissions', [])) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="permission_{{ $permission->id }}">
                                        {{ ucwords(str_replace('_', ' ', $permission->name)) }}
                                    </label>
                                </div>
                            @endforeach
                        </div>

                        <div class="form-group mt-4">
                            <button type="submit" class="btn btn-primary">Create Admin User</button>
                            <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
