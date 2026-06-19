@extends('layouts.auth', ['title' => 'Login', 'subtitle' => 'Sign in to your account', 'showSidebar' => true])

@section('content')
<form method="POST" action="{{ route('login') }}">
    @csrf
    
    <div class="mb-3">
        <label for="email" class="form-label">Email Address</label>
        <input id="email" type="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus placeholder="Enter your email">
    </div>
    
    <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input id="password" type="password" name="password" class="form-control" required placeholder="Enter your password">
    </div>
    
    <div class="mb-3 d-flex justify-content-between align-items-center">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="remember" id="remember">
            <label class="form-check-label" for="remember">
                Remember me
            </label>
        </div>
        <a href="{{ route('password.request') }}" class="text-primary">Forgot password?</a>
    </div>
    
    <div class="d-grid">
        <button type="submit" class="btn btn-primary">
            Sign In
        </button>
    </div>
</form>
@endsection