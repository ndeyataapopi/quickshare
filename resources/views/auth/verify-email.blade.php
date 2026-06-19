@extends('layouts.auth', ['title' => 'Verify Email', 'subtitle' => 'Confirm your email address', 'showSidebar' => false])

@section('content')
<div class="text-center mb-4">
    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-info text-white mb-3" style="width:64px;height:64px;font-size:28px;">
        <i class="mdi mdi-email-check"></i>
    </div>
    <p class="text-muted">Thanks for signing up! Please verify your email address by clicking the link we sent you. If you didn't receive it, we can send another.</p>
</div>

@if (session('status') == 'verification-link-sent')
    <div class="alert alert-success">A new verification link has been sent to your email address.</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ route('verification.send') }}" class="mb-3">
    @csrf
    <div class="d-grid">
        <button type="submit" class="btn btn-primary">
            <i class="mdi mdi-email-send mr-1"></i> Resend Verification Email
        </button>
    </div>
</form>

<form method="POST" action="{{ route('logout') }}">
    @csrf
    <div class="d-grid">
        <button type="submit" class="btn btn-outline-secondary">
            <i class="mdi mdi-logout mr-1"></i> Log Out
        </button>
    </div>
</form>
@endsection
