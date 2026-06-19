<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }} - Authentication</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('assets/images/favicon.png') }}">
    
    <!-- Bootstrap CSS -->
    <link href="{{ asset('assets/libs/bootstrap/dist/css/bootstrap.min.css') }}" rel="stylesheet">
    
    <!-- Ample Admin CSS -->
    <link href="{{ asset('dist/css/style.min.css') }}" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .auth-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .auth-sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .auth-form {
            padding: 40px;
        }
        .auth-logo {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 30px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }
        .form-control {
            border-radius: 5px;
            padding: 12px;
            border: 1px solid #ddd;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-8">
                <div class="auth-card">
                    <div class="row g-0">
                        @if(isset($showSidebar) && $showSidebar)
                        <div class="col-md-5 auth-sidebar d-none d-md-block">
                            <h2>Welcome to QuickShare</h2>
                            <p class="mt-3">Your trusted peer-to-peer lending platform. Connect borrowers with lenders seamlessly.</p>
                            <div class="mt-5">
                                <ul class="list-unstyled">
                                    <li class="mb-3"><i class="fas fa-check-circle me-2"></i> Fast Loan Approval</li>
                                    <li class="mb-3"><i class="fas fa-check-circle me-2"></i> Secure Transactions</li>
                                    <li class="mb-3"><i class="fas fa-check-circle me-2"></i> Transparent Process</li>
                                    <li class="mb-3"><i class="fas fa-check-circle me-2"></i> 24/7 Support</li>
                                </ul>
                            </div>
                        </div>
                        @endif
                        <div class="{{ isset($showSidebar) && $showSidebar ? 'col-md-7' : 'col-12' }} auth-form">
                            <div class="text-center mb-4">
                                <h3 class="auth-logo">QuickShare</h3>
                                <p class="text-muted">{{ $subtitle ?? 'Sign in to continue' }}</p>
                            </div>
                            
                            @if(session('status'))
                                <div class="alert alert-success">
                                    {{ session('status') }}
                                </div>
                            @endif
                            
                            @if($errors->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        @foreach($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            
                            @yield('content')
                            
                            <div class="text-center mt-4">
                                <p class="text-muted">
                                    @if(request()->routeIs('login'))
                                        Don't have an account? <a href="{{ route('register') }}" class="text-primary">Sign up</a>
                                    @elseif(request()->routeIs('register'))
                                        Already have an account? <a href="{{ route('login') }}" class="text-primary">Sign in</a>
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="{{ asset('assets/libs/jquery/dist/jquery.min.js') }}"></script>
    <!-- Bootstrap tether Core JavaScript -->
    <script src="{{ asset('assets/libs/popper.js/dist/umd/popper.min.js') }}"></script>
    <script src="{{ asset('assets/libs/bootstrap/dist/js/bootstrap.min.js') }}"></script>
    
    @stack('scripts')
</body>

</html>
