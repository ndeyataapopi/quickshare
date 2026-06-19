<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>How It Works - {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('dist/css/style.min.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/MaterialDesign-Webfont/5.3.45/css/materialdesignicons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>body{font-family:'Nunito',sans-serif;} .hero-section{background:linear-gradient(135deg,#4f9ef8 0%,#7c3aed 100%);padding:80px 0;} .step-badge{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:16px;margin:0 auto 12px;}</style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background:rgba(0,0,0,.15);position:absolute;width:100%;z-index:10;">
        <div class="container">
            <a class="navbar-brand font-weight-bold" href="{{ url('/') }}"><span class="badge badge-light text-primary mr-1" style="font-size:14px">QS</span> QuickShare</a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#nav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="nav">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item"><a class="nav-link" href="{{ url('/') }}">Home</a></li>
                    <li class="nav-item"><a class="nav-link active" href="{{ route('how-it-works') }}">How It Works</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('faq') }}">FAQ</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Login</a></li>
                    <li class="nav-item"><a class="nav-link btn btn-light text-primary px-3 ml-2 rounded" href="{{ route('register') }}">Get Started</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <section class="hero-section text-white text-center"><div class="container pt-5"><h1 class="display-4 font-weight-bold mb-3">How QuickShare Works</h1><p class="lead mb-0">Simple, transparent, and secure peer-to-peer lending in 4 easy steps.</p></div></section>

    <section class="py-5 bg-white">
        <div class="container">
            <h3 class="text-center font-weight-bold mb-5">For Borrowers</h3>
            <div class="row text-center">
                <div class="col-6 col-md-3 mb-4"><div class="step-badge bg-primary">1</div><i class="mdi mdi-account-plus text-primary" style="font-size:36px"></i><h6 class="font-weight-bold mt-2">Create Account</h6><p class="text-muted small">Sign up and complete KYC to build your profile.</p></div>
                <div class="col-6 col-md-3 mb-4"><div class="step-badge bg-success">2</div><i class="mdi mdi-file-document text-success" style="font-size:36px"></i><h6 class="font-weight-bold mt-2">Request Loan</h6><p class="text-muted small">Specify amount and repayment period.</p></div>
                <div class="col-6 col-md-3 mb-4"><div class="step-badge bg-info">3</div><i class="mdi mdi-cash text-info" style="font-size:36px"></i><h6 class="font-weight-bold mt-2">Get Funded</h6><p class="text-muted small">Lenders fund your request. Receive money directly.</p></div>
                <div class="col-6 col-md-3 mb-4"><div class="step-badge bg-warning">4</div><i class="mdi mdi-calendar-check text-warning" style="font-size:36px"></i><h6 class="font-weight-bold mt-2">Repay Flexibly</h6><p class="text-muted small">Repay and build your trust score for better rates.</p></div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-light">
        <div class="container">
            <h3 class="text-center font-weight-bold mb-5">For Lenders</h3>
            <div class="row text-center">
                <div class="col-6 col-md-3 mb-4"><div class="step-badge bg-primary">1</div><i class="mdi mdi-account-plus text-primary" style="font-size:36px"></i><h6 class="font-weight-bold mt-2">Create Account</h6><p class="text-muted small">Sign up and verify your identity.</p></div>
                <div class="col-6 col-md-3 mb-4"><div class="step-badge bg-success">2</div><i class="mdi mdi-store text-success" style="font-size:36px"></i><h6 class="font-weight-bold mt-2">Browse Marketplace</h6><p class="text-muted small">Filter loans by risk, return, and trust score.</p></div>
                <div class="col-6 col-md-3 mb-4"><div class="step-badge bg-info">3</div><i class="mdi mdi-bank-transfer text-info" style="font-size:36px"></i><h6 class="font-weight-bold mt-2">Fund Loans</h6><p class="text-muted small">Contribute to loans that match your risk appetite.</p></div>
                <div class="col-6 col-md-3 mb-4"><div class="step-badge bg-warning">4</div><i class="mdi mdi-trending-up text-warning" style="font-size:36px"></i><h6 class="font-weight-bold mt-2">Earn Returns</h6><p class="text-muted small">Receive repayments with interest to your account.</p></div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-white">
        <div class="container">
            <h3 class="text-center font-weight-bold mb-5">Trust Score System</h3>
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <p class="text-muted text-center mb-4">Our proprietary trust score ensures responsible borrowing and lending. Build your score to access better rates and higher loan limits.</p>
                    <div class="row">
                        <div class="col-6 col-md-3 text-center mb-3"><div class="badge badge-secondary px-3 py-2 mb-2" style="font-size:14px">Bronze</div><div class="text-muted small">0–49 · Basic access</div></div>
                        <div class="col-6 col-md-3 text-center mb-3"><div class="badge badge-light px-3 py-2 mb-2" style="font-size:14px;border:1px solid #ccc">Silver</div><div class="text-muted small">50–69 · Standard rates</div></div>
                        <div class="col-6 col-md-3 text-center mb-3"><div class="badge badge-warning px-3 py-2 mb-2" style="font-size:14px">Gold</div><div class="text-muted small">70–84 · Preferred rates</div></div>
                        <div class="col-6 col-md-3 text-center mb-3"><div class="badge badge-primary px-3 py-2 mb-2" style="font-size:14px">Platinum</div><div class="text-muted small">85–100 · Premium</div></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 text-center text-white" style="background:linear-gradient(135deg,#4f9ef8 0%,#7c3aed 100%)">
        <div class="container"><h3 class="font-weight-bold mb-3">Ready to Get Started?</h3><a href="{{ route('register') }}" class="btn btn-light btn-lg text-primary font-weight-bold px-5">Create Your Account</a></div>
    </section>

    <footer class="bg-dark text-white py-4 text-center"><small>&copy; {{ date('Y') }} QuickShare. All rights reserved.</small></footer>
    <script src="{{ asset('dist/js/jquery.min.js') }}"></script>
    <script src="{{ asset('dist/js/bootstrap.bundle.min.js') }}"></script>
</body>
</html>
