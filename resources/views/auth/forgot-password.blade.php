@extends('layouts.auth', ['title' => 'Forgot Password', 'subtitle' => 'Reset your password', 'showSidebar' => false])

@section('content')
<form method="POST" action="{{ route('password.email') }}">
    @csrf
    
    <div class="mb-3">
        <label for="email" class="form-label">Email Address</label>
        <input id="email" type="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus placeholder="Enter your email">
    </div>
    
    <div class="d-grid">
        <button type="submit" class="btn btn-primary">
            Send Reset Link
        </button>
    </div>
</form>
@endsection
