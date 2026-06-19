<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 - Forbidden | QuickShare</title>
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('assets/images/favicon.png') }}">
    <link href="{{ asset('dist/css/style.min.css') }}" rel="stylesheet">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #f4f6f9; }
        .error-box { text-align: center; padding: 60px 40px; background: #fff; border-radius: 8px; box-shadow: 0 2px 20px rgba(0,0,0,.08); max-width: 500px; width: 100%; }
        .error-code { font-size: 120px; font-weight: 700; color: #e53935; line-height: 1; }
    </style>
</head>
<body>
    <div class="error-box">
        <div class="error-code">403</div>
        <h3 class="mt-3 mb-2">Access Forbidden</h3>
        <p class="text-muted mb-4">{{ $exception->getMessage() ?: 'You do not have permission to access this resource.' }}</p>
        <a href="{{ url('/dashboard') }}" class="btn btn-primary btn-lg mr-2">Go to Dashboard</a>
        <a href="javascript:history.back()" class="btn btn-outline-secondary btn-lg">Go Back</a>
    </div>
</body>
</html>
