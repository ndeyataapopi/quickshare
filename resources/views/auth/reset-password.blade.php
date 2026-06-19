@extends('layouts.auth', ['title' => 'Reset Password', 'subtitle' => 'Set your new password', 'showSidebar' => false])

@section('content')
<form method="POST" action="{{ route('password.store') }}">
    @csrf
    
    <input type="hidden" name="token" value="{{ $request->route('token') }}">
    
    <div class="mb-3">
        <label for="email" class="form-label">Email Address</label>
        <input id="email" type="email" name="email" class="form-control" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username" placeholder="Enter your email">
    </div>
    
    <div class="mb-3">
        <label for="password" class="form-label">New Password</label>
        <input id="password" type="password" name="password" class="form-control" required autocomplete="new-password" placeholder="Min 8 characters">
    </div>
    
    <div class="mb-3">
        <label for="password_confirmation" class="form-label">Confirm Password</label>
        <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required autocomplete="new-password" placeholder="Confirm new password">
    </div>
    
    <div class="d-grid">
        <button type="submit" class="btn btn-primary">
            Reset Password
        </button>
    </div>
</form>
@endsection
