@extends('layouts.auth', ['title' => 'Confirm Password', 'subtitle' => 'Secure area — please re-enter your password', 'showSidebar' => false])

@section('content')
<div class="text-center mb-4">
    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-warning text-white mb-3" style="width:64px;height:64px;font-size:28px;">
        <i class="mdi mdi-lock"></i>
    </div>
    <p class="text-muted">This is a secure area. Please confirm your password before continuing.</p>
</div>

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ route('password.confirm') }}">
    @csrf
    <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="current-password" placeholder="Enter your password">
        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="d-grid">
        <button type="submit" class="btn btn-warning text-white">
            <i class="mdi mdi-lock-check mr-1"></i> Confirm Password
        </button>
    </div>
</form>
@endsection
