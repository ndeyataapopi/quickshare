<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terms of Service - {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('dist/css/style.min.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/MaterialDesign-Webfont/5.3.45/css/materialdesignicons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>body{font-family:'Nunito',sans-serif;} .hero-section{background:linear-gradient(135deg,#4f9ef8 0%,#7c3aed 100%);padding:80px 0;}</style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background:rgba(0,0,0,.15);position:absolute;width:100%;z-index:10;">
        <div class="container">
            <a class="navbar-brand font-weight-bold" href="{{ url('/') }}"><span class="badge badge-light text-primary mr-1" style="font-size:14px">QS</span> QuickShare</a>
            <div class="collapse navbar-collapse"><ul class="navbar-nav ml-auto">
                <li class="nav-item"><a class="nav-link" href="{{ url('/') }}">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('privacy') }}">Privacy</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Login</a></li>
            </ul></div>
        </div>
    </nav>
    <section class="hero-section text-white text-center"><div class="container pt-5"><h1 class="display-4 font-weight-bold mb-3">Terms of Service</h1><p class="lead mb-0">Please read these terms carefully before using QuickShare.</p></div></section>

    <section class="py-5 bg-white">
        <div class="container"><div class="row justify-content-center"><div class="col-lg-8">
            <h5 class="font-weight-bold mt-4">1. Acceptance of Terms</h5>
            <p class="text-muted">By accessing or using QuickShare, you agree to be bound by these Terms of Service.</p>
            <h5 class="font-weight-bold mt-4">2. Eligibility</h5>
            <p class="text-muted">You must be at least 18 years old and legally capable of entering a binding contract to use QuickShare.</p>
            <h5 class="font-weight-bold mt-4">3. Account Registration</h5>
            <p class="text-muted">You agree to provide accurate, complete, and current information during registration and are responsible for maintaining your account credentials.</p>
            <h5 class="font-weight-bold mt-4">4. Borrowing Terms</h5>
            <ul class="text-muted"><li>Loan requests are subject to lender approval</li><li>Interest rates are based on your trust score, loan amount, and term</li><li>You agree to repay loans per the agreed schedule</li><li>Failure to repay may result in collections action and trust score impact</li></ul>
            <h5 class="font-weight-bold mt-4">5. Lending Terms</h5>
            <ul class="text-muted"><li>Lending involves risk, including borrower default risk</li><li>Returns are not guaranteed</li><li>Diversify investments across multiple loans to mitigate risk</li><li>QuickShare is not responsible for investment losses</li></ul>
            <h5 class="font-weight-bold mt-4">6. Trust Score</h5>
            <p class="text-muted">QuickShare uses a proprietary trust score based on repayment history, KYC, account age, and other factors. Higher scores qualify for better rates.</p>
            <h5 class="font-weight-bold mt-4">7. Fees</h5>
            <p class="text-muted">QuickShare charges platform fees on successful loan transactions. Fees are displayed before confirming any transaction.</p>
            <h5 class="font-weight-bold mt-4">8. Prohibited Activities</h5>
            <p class="text-muted">You agree not to use QuickShare for fraud, money laundering, or any illegal activity.</p>
            <h5 class="font-weight-bold mt-4">9. Limitation of Liability</h5>
            <p class="text-muted">QuickShare shall not be liable for indirect, incidental, or consequential damages arising from use of our platform.</p>
            <h5 class="font-weight-bold mt-4">10. Contact Us</h5>
            <p class="text-muted">For questions: <a href="mailto:support@quickshare.com">support@quickshare.com</a></p>
            <p class="text-muted"><small>Last updated: {{ date('F j, Y') }}</small></p>
        </div></div></div>
    </section>

    <footer class="bg-dark text-white py-4 text-center"><small>&copy; {{ date('Y') }} QuickShare. All rights reserved.</small></footer>
    <script src="{{ asset('dist/js/jquery.min.js') }}"></script>
    <script src="{{ asset('dist/js/bootstrap.bundle.min.js') }}"></script>
</body>
</html>
