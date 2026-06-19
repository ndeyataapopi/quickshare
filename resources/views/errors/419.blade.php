<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>419 - Session Expired | QuickShare</title>
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('assets/images/favicon.png') }}">
    <link href="{{ asset('dist/css/style.min.css') }}" rel="stylesheet">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #f4f6f9; }
        .error-box { text-align: center; padding: 60px 40px; background: #fff; border-radius: 8px; box-shadow: 0 2px 20px rgba(0,0,0,.08); max-width: 500px; width: 100%; }
        .error-code { font-size: 120px; font-weight: 700; color: #ff9800; line-height: 1; }
    </style>
</head>
<body>
    <div class="error-box">
        <div class="error-code">419</div>
        <h3 class="mt-3 mb-2">Session Expired</h3>
        <p class="text-muted mb-4">Your session has expired. Please refresh the page and try again.</p>
        <a href="{{ url()->previous() }}" class="btn btn-primary btn-lg mr-2">Go Back</a>
        <a href="{{ route('login') }}" class="btn btn-outline-secondary btn-lg">Log In Again</a>
    </div>
</body>
</html>
