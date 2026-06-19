<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Policy - {{ config('app.name') }}</title>
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
                <li class="nav-item"><a class="nav-link" href="{{ route('terms') }}">Terms</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Login</a></li>
            </ul></div>
        </div>
    </nav>
    <section class="hero-section text-white text-center"><div class="container pt-5"><h1 class="display-4 font-weight-bold mb-3">Privacy Policy</h1><p class="lead mb-0">Your privacy is important to us.</p></div></section>

    <section class="py-5 bg-white">
        <div class="container"><div class="row justify-content-center"><div class="col-lg-8">
            <h5 class="font-weight-bold mt-4">1. Information We Collect</h5>
            <p class="text-muted">QuickShare collects personal information (name, email, phone, date of birth), identification documents, financial information, and transaction data.</p>
            <h5 class="font-weight-bold mt-4">2. How We Use Your Information</h5>
            <p class="text-muted">We use your data to provide services, process transactions, verify identity, communicate with you, comply with legal obligations, and prevent fraud.</p>
            <h5 class="font-weight-bold mt-4">3. Data Security</h5>
            <p class="text-muted">We implement 256-bit SSL encryption, encrypt sensitive data at rest, conduct regular security audits, and restrict access to personal data.</p>
            <h5 class="font-weight-bold mt-4">4. Data Sharing</h5>
            <p class="text-muted">We do not sell your personal information. We only share data with your consent, with lenders when required for a loan, with service providers under strict confidentiality, or when required by law.</p>
            <h5 class="font-weight-bold mt-4">5. Your Rights</h5>
            <p class="text-muted">You have the right to access, update, and delete your personal information, opt out of marketing communications, and request data portability.</p>
            <h5 class="font-weight-bold mt-4">6. Cookies</h5>
            <p class="text-muted">We use cookies to improve your experience, analyze usage, and for security. You can control cookie settings through your browser.</p>
            <h5 class="font-weight-bold mt-4">7. Data Retention</h5>
            <p class="text-muted">We retain your information only as long as necessary to provide services and comply with legal obligations.</p>
            <h5 class="font-weight-bold mt-4">8. Contact Us</h5>
            <p class="text-muted">For privacy questions: <a href="mailto:privacy@quickshare.com">privacy@quickshare.com</a></p>
            <p class="text-muted"><small>Last updated: {{ date('F j, Y') }}</small></p>
        </div></div></div>
    </section>

    <footer class="bg-dark text-white py-4 text-center"><small>&copy; {{ date('Y') }} QuickShare. All rights reserved.</small></footer>
    <script src="{{ asset('dist/js/jquery.min.js') }}"></script>
    <script src="{{ asset('dist/js/bootstrap.bundle.min.js') }}"></script>
</body>
</html>
