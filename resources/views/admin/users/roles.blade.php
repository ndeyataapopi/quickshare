@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0">Roles & Permissions</h5>
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
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Assigned Roles</h5>
                    <form method="POST" action="{{ route('admin.users.roles.update', $user) }}">
                        @csrf @method('PATCH')
                        <div class="form-group">
                            @foreach($roles as $role)
                                <div class="custom-control custom-checkbox mb-2">
                                    <input type="checkbox" name="roles[]" value="{{ $role->name }}" id="role_{{ $role->id }}" class="custom-control-input" {{ $user->hasRole($role->name) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="role_{{ $role->id }}">
                                        <span class="badge badge-info">{{ ucfirst($role->name) }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Update roles?')">Update Roles</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title text-uppercase mb-3">Assigned Permissions</h5>
                    <form method="POST" action="{{ route('admin.users.permissions.update', $user) }}">
                        @csrf @method('PATCH')
                        <div class="form-group" style="max-height: 400px; overflow-y: auto;">
                            @foreach($permissions as $permission)
                                <div class="custom-control custom-checkbox mb-2">
                                    <input type="checkbox" name="permissions[]" value="{{ $permission->name }}" id="permission_{{ $permission->id }}" class="custom-control-input" {{ $user->hasDirectPermission($permission->name) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="permission_{{ $permission->id }}">
                                        {{ ucwords(str_replace('_', ' ', $permission->name)) }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Update permissions?')">Update Permissions</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="mt-3">
        <a href="{{ route('admin.users.show', $user) }}" class="btn btn-outline-secondary"><i class="mdi mdi-arrow-left mr-1"></i> Back to User</a>
    </div>
</div>
@endsection
