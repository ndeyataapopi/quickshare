<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>About QuickShare</title>
    <link rel="stylesheet" href="{{ asset('dist/css/style.min.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/MaterialDesign-Webfont/5.3.45/css/materialdesignicons.min.css">
    <style>
        .hero-section { background: linear-gradient(135deg, #4f9ef8 0%, #7c3aed 100%); padding: 80px 0; }
        .value-card { transition: transform .2s; }
        .value-card:hover { transform: translateY(-4px); }
        .team-avatar { width: 100px; height: 100px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; color: #fff; margin: 0 auto 16px; }
        .cta-section { background: linear-gradient(135deg, #4f9ef8 0%, #7c3aed 100%); padding: 80px 0; }
        body { font-family: 'Nunito', sans-serif; }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: rgba(0,0,0,.15); position: absolute; width:100%; z-index:10;">
        <div class="container">
            <a class="navbar-brand font-weight-bold" href="{{ route('home') ?? '/' }}">
                <span class="badge badge-light text-primary mr-1" style="font-size:14px">QS</span> QuickShare
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#mainNav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item"><a class="nav-link" href="{{ url('/') }}">Home</a></li>
                    <li class="nav-item"><a class="nav-link active" href="{{ route('about') }}">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Login</a></li>
                    <li class="nav-item"><a class="nav-link btn btn-light text-primary px-3 ml-2 rounded" href="{{ route('register') }}">Get Started</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero -->
    <section class="hero-section text-white text-center">
        <div class="container pt-5">
            <h1 class="display-4 font-weight-bold mb-3">About QuickShare</h1>
            <p class="lead mb-0" style="max-width:600px;margin:0 auto">Empowering Namibians with accessible, transparent, and secure peer-to-peer lending.</p>
        </div>
    </section>

    <!-- Mission -->
    <section class="py-5 bg-white">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center">
                    <h2 class="font-weight-bold mb-4">Our Mission</h2>
                    <p class="lead text-muted">To democratize access to credit in Namibia by connecting borrowers and lenders directly, eliminating traditional banking barriers, and creating a transparent, efficient, and inclusive financial ecosystem.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Values -->
    <section class="py-5 bg-light">
        <div class="container">
            <h2 class="font-weight-bold text-center mb-5">Our Values</h2>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card h-100 value-card shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="mb-3"><i class="mdi mdi-shield-check text-primary" style="font-size:48px"></i></div>
                            <h5 class="font-weight-bold">Trust</h5>
                            <p class="text-muted">Building trust through transparency, security, and fair practices for every transaction.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 value-card shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="mb-3"><i class="mdi mdi-lightbulb-on text-warning" style="font-size:48px"></i></div>
                            <h5 class="font-weight-bold">Innovation</h5>
                            <p class="text-muted">Leveraging technology to make lending simple, fast, and accessible to all.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 value-card shadow-sm">
                        <div class="card-body text-center p-4">
                            <div class="mb-3"><i class="mdi mdi-account-group text-success" style="font-size:48px"></i></div>
                            <h5 class="font-weight-bold">Community</h5>
                            <p class="text-muted">Fostering a community of responsible borrowers and lenders who grow together.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Team -->
    <section class="py-5 bg-white">
        <div class="container">
            <h2 class="font-weight-bold text-center mb-5">Our Team</h2>
            <div class="row justify-content-center">
                <div class="col-6 col-md-3 text-center mb-4">
                    <div class="team-avatar" style="background: linear-gradient(135deg,#4f9ef8,#7c3aed)">JD</div>
                    <h6 class="font-weight-bold mb-0">John Doe</h6>
                    <small class="text-muted">CEO &amp; Founder</small>
                </div>
                <div class="col-6 col-md-3 text-center mb-4">
                    <div class="team-avatar" style="background: linear-gradient(135deg,#22c55e,#0d9488)">JS</div>
                    <h6 class="font-weight-bold mb-0">Jane Smith</h6>
                    <small class="text-muted">CTO</small>
                </div>
                <div class="col-6 col-md-3 text-center mb-4">
                    <div class="team-avatar" style="background: linear-gradient(135deg,#f97316,#ef4444)">MK</div>
                    <h6 class="font-weight-bold mb-0">Michael Kim</h6>
                    <small class="text-muted">Head of Operations</small>
                </div>
                <div class="col-6 col-md-3 text-center mb-4">
                    <div class="team-avatar" style="background: linear-gradient(135deg,#ec4899,#7c3aed)">SA</div>
                    <h6 class="font-weight-bold mb-0">Sarah Adams</h6>
                    <small class="text-muted">Head of Risk</small>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="cta-section text-white text-center">
        <div class="container">
            <h2 class="font-weight-bold mb-3">Join Our Mission</h2>
            <p class="lead mb-4">Be part of the financial revolution in Namibia.</p>
            <a href="{{ route('register') }}" class="btn btn-light btn-lg text-primary font-weight-bold px-5">Get Started Today</a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 text-center">
        <small>&copy; {{ date('Y') }} QuickShare. All rights reserved.</small>
    </footer>

    <script src="{{ asset('dist/js/jquery.min.js') }}"></script>
    <script src="{{ asset('dist/js/bootstrap.bundle.min.js') }}"></script>
</body>
</html>
