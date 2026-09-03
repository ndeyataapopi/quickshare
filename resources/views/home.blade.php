<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>QuickShare — Peer-to-Peer Lending for Namibia</title>
    <meta name="description" content="QuickShare is a Namibian peer-to-peer lending platform connecting borrowers and lenders. Borrow money quickly or fund verified borrowers and earn returns." />
    <meta name="keywords" content="peer-to-peer lending, Namibia, fintech, P2P lending, borrow money, lend money, QuickShare, Namibian loans" />
    <meta name="author" content="QuickShare Namibia" />
    <meta name="robots" content="index, follow" />
    <link rel="canonical" href="{{ url('/') }}" />

    <!-- OpenGraph -->
    <meta property="og:type" content="website" />
    <meta property="og:title" content="QuickShare — Peer-to-Peer Lending for Namibia" />
    <meta property="og:description" content="A Namibian peer-to-peer lending platform connecting borrowers and lenders with trust, transparency, and technology." />
    <meta property="og:url" content="{{ url('/') }}" />
    <meta property="og:site_name" content="QuickShare" />
    <meta property="og:locale" content="en_NA" />

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="QuickShare — Peer-to-Peer Lending for Namibia" />
    <meta name="twitter:description" content="A Namibian peer-to-peer lending platform connecting borrowers and lenders with trust, transparency, and technology." />

    <!-- Schema.org -->
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "Organization",
        "name": "QuickShare",
        "description": "A Namibian peer-to-peer lending platform connecting borrowers and lenders.",
        "url": "{{ url('/') }}",
        "foundingDate": "2026",
        "address": {
            "@@type": "PostalAddress",
            "addressCountry": "NA"
        }
    }
    </script>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>

    <style>
        :root{
            --primary:#635bff;
            --primary-dark:#4f46e5;
            --secondary:#14b8a6;
            --accent:#06b6d4;
            --dark:#0f172a;
            --dark-2:#1e293b;
            --light:#f8fafc;
            --muted:#64748b;
            --border:#e2e8f0;
            --success:#10b981;
            --warning:#f59e0b;
            --danger:#ef4444;
            --white:#ffffff;

            --shadow-lg:0 20px 45px rgba(15,23,42,.08);
            --shadow-md:0 10px 25px rgba(15,23,42,.06);

            --radius-xl:24px;
            --radius-lg:18px;
            --radius-md:14px;
        }

        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
        }

        html{
            scroll-behavior:smooth;
        }

        body{
            font-family:'Inter',sans-serif;
            background:#f4f7fb;
            color:var(--dark);
            overflow-x:hidden;
        }

        a{
            text-decoration:none;
        }

        .container{
            width:min(1200px,90%);
            margin:auto;
        }

        /* NAVBAR */

        .navbar{
            position:fixed;
            top:0;
            left:0;
            width:100%;
            z-index:999;
            transition:.3s ease;
            padding:18px 0;
        }

        .navbar.scrolled{
            background:rgba(255,255,255,.95);
            backdrop-filter:blur(16px);
            box-shadow:0 5px 25px rgba(0,0,0,.05);
        }

        .nav-wrapper{
            display:flex;
            justify-content:space-between;
            align-items:center;
        }

        .logo{
            display:flex;
            align-items:center;
            gap:12px;
            color:white;
            font-weight:800;
            font-size:1.4rem;
        }

        .navbar.scrolled .logo{
            color:var(--dark);
        }

        .logo-icon{
            width:45px;
            height:45px;
            border-radius:14px;
            background:linear-gradient(135deg,var(--primary),var(--secondary));
            display:flex;
            align-items:center;
            justify-content:center;
            color:white;
            font-size:1.2rem;
            box-shadow:0 10px 20px rgba(99,91,255,.25);
        }

        .nav-links{
            display:flex;
            align-items:center;
            gap:35px;
        }

        .nav-links a{
            color:white;
            font-weight:500;
            transition:.3s;
        }

        .navbar.scrolled .nav-links a{
            color:var(--dark);
        }

        .nav-links a:hover{
            color:var(--secondary);
        }

        .nav-buttons{
            display:flex;
            gap:15px;
        }

        .btn{
            padding:14px 26px;
            border-radius:14px;
            font-weight:600;
            transition:.3s ease;
            display:inline-flex;
            align-items:center;
            gap:10px;
            border:none;
            cursor:pointer;
        }

        .btn-primary{
            background:linear-gradient(135deg,var(--primary),var(--accent));
            color:white;
            box-shadow:0 10px 25px rgba(99,91,255,.35);
        }

        .btn-primary:hover{
            transform:translateY(-3px);
        }

        .btn-outline{
            border:1px solid rgba(255,255,255,.3);
            color:white;
            background:transparent;
        }

        .navbar.scrolled .btn-outline{
            border:1px solid var(--border);
            color:var(--dark);
        }

        .btn-outline:hover{
            background:white;
            color:var(--primary);
        }

        /* HERO */

        .hero{
            min-height:100vh;
            background:
                radial-gradient(circle at top right, rgba(20,184,166,.25), transparent 30%),
                radial-gradient(circle at bottom left, rgba(99,91,255,.35), transparent 30%),
                linear-gradient(135deg,#0f172a,#111827,#1e1b4b);
            position:relative;
            overflow:hidden;
            display:flex;
            align-items:center;
        }

        .hero::before{
            content:'';
            position:absolute;
            width:700px;
            height:700px;
            background:rgba(255,255,255,.03);
            border-radius:50%;
            top:-300px;
            right:-250px;
        }

        .hero-grid{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:60px;
            align-items:center;
            position:relative;
            z-index:5;
            padding-top:120px;
        }

        .hero-text h1{
            color:white;
            font-size:4.3rem;
            line-height:1.05;
            margin-bottom:25px;
            font-weight:900;
        }

        .hero-text h1 span{
            background:linear-gradient(135deg,#22d3ee,#14b8a6);
            -webkit-background-clip:text;
            -webkit-text-fill-color:transparent;
        }

        .hero-text p{
            color:#cbd5e1;
            font-size:1.15rem;
            line-height:1.8;
            margin-bottom:35px;
            max-width:650px;
        }

        .hero-buttons{
            display:flex;
            gap:18px;
            flex-wrap:wrap;
        }

        .hero-stats{
            margin-top:50px;
            display:flex;
            gap:40px;
            flex-wrap:wrap;
        }

        .stat h3{
            color:white;
            font-size:2rem;
            margin-bottom:5px;
        }

        .stat p{
            color:#94a3b8;
            font-size:.95rem;
        }

        /* DASHBOARD MOCKUP */

        .dashboard{
            background:rgba(255,255,255,.06);
            border:1px solid rgba(255,255,255,.08);
            border-radius:30px;
            padding:25px;
            backdrop-filter:blur(20px);
            box-shadow:0 25px 60px rgba(0,0,0,.35);
        }

        .dashboard-top{
            display:flex;
            justify-content:space-between;
            margin-bottom:25px;
        }

        .balance-card{
            background:linear-gradient(135deg,var(--primary),#4338ca);
            border-radius:24px;
            padding:28px;
            color:white;
            margin-bottom:25px;
        }

        .balance-card small{
            opacity:.8;
        }

        .balance-card h2{
            margin:12px 0;
            font-size:2.5rem;
        }

        .loan-cards{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:18px;
        }

        .loan-card{
            background:white;
            border-radius:18px;
            padding:20px;
        }

        .loan-card h4{
            margin-bottom:12px;
        }

        .progress{
            height:10px;
            background:#e2e8f0;
            border-radius:20px;
            overflow:hidden;
            margin:15px 0;
        }

        .progress span{
            display:block;
            height:100%;
            border-radius:20px;
            background:linear-gradient(135deg,var(--secondary),var(--accent));
        }

        /* SECTION */

        section{
            padding:100px 0;
        }

        .section-header{
            text-align:center;
            margin-bottom:70px;
        }

        .section-header span{
            color:var(--primary);
            font-weight:700;
            text-transform:uppercase;
            letter-spacing:1px;
            font-size:.85rem;
        }

        .section-header h2{
            font-size:3rem;
            margin:18px 0;
        }

        .section-header p{
            max-width:750px;
            margin:auto;
            color:var(--muted);
            line-height:1.8;
            font-size:1.05rem;
        }

        /* FEATURES */

        .features{
            background:white;
        }

        .feature-grid{
            display:grid;
            grid-template-columns:repeat(3,1fr);
            gap:30px;
        }

        .feature-card{
            background:#fff;
            padding:35px;
            border-radius:var(--radius-xl);
            border:1px solid #edf2f7;
            transition:.35s ease;
            position:relative;
            overflow:hidden;
        }

        .feature-card:hover{
            transform:translateY(-8px);
            box-shadow:var(--shadow-lg);
        }

        .feature-icon{
            width:70px;
            height:70px;
            border-radius:20px;
            background:linear-gradient(135deg,var(--primary),var(--accent));
            color:white;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:1.5rem;
            margin-bottom:25px;
        }

        .feature-card h3{
            margin-bottom:15px;
            font-size:1.35rem;
        }

        .feature-card p{
            color:var(--muted);
            line-height:1.8;
        }

        /* HOW */

        .how{
            background:linear-gradient(to bottom,#f8fafc,#eef4ff);
        }

        .steps{
            display:grid;
            grid-template-columns:repeat(4,1fr);
            gap:25px;
        }

        .step{
            background:white;
            padding:35px 25px;
            border-radius:var(--radius-xl);
            text-align:center;
            position:relative;
            box-shadow:var(--shadow-md);
        }

        .step-number{
            width:55px;
            height:55px;
            border-radius:50%;
            background:linear-gradient(135deg,var(--primary),var(--secondary));
            color:white;
            display:flex;
            align-items:center;
            justify-content:center;
            font-weight:800;
            margin:0 auto 20px;
            font-size:1.1rem;
        }

        .step h4{
            margin-bottom:14px;
        }

        .step p{
            color:var(--muted);
            line-height:1.7;
        }

        /* TRUST */

        .trust{
            background:white;
        }

        .trust-wrapper{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:60px;
            align-items:center;
        }

        .trust-card{
            background:linear-gradient(135deg,#0f172a,#1e293b);
            border-radius:30px;
            padding:45px;
            color:white;
        }

        .trust-list{
            margin-top:30px;
        }

        .trust-list li{
            list-style:none;
            margin-bottom:18px;
            display:flex;
            align-items:center;
            gap:15px;
            color:#cbd5e1;
        }

        .trust-list i{
            color:var(--success);
        }

        /* FAQ */

        .faq{
            background:#f8fafc;
        }

        .faq-wrapper{
            max-width:900px;
            margin:auto;
        }

        .faq-item{
            background:white;
            border-radius:18px;
            margin-bottom:18px;
            overflow:hidden;
            box-shadow:0 5px 15px rgba(15,23,42,.04);
        }

        .faq-question{
            padding:24px;
            display:flex;
            justify-content:space-between;
            align-items:center;
            cursor:pointer;
            font-weight:600;
        }

        .faq-answer{
            max-height:0;
            overflow:hidden;
            transition:max-height .3s ease;
        }

        .faq-answer p{
            padding:0 24px 24px;
            color:var(--muted);
            line-height:1.8;
        }

        .faq-item.active .faq-answer{
            max-height:300px;
        }

        /* CTA */

        .cta{
            background:
                radial-gradient(circle at top left, rgba(255,255,255,.08), transparent 25%),
                linear-gradient(135deg,var(--primary),#4338ca,#0f172a);
            color:white;
            text-align:center;
        }

        .cta h2{
            font-size:3rem;
            margin-bottom:20px;
        }

        .cta p{
            max-width:700px;
            margin:0 auto 40px;
            color:#dbeafe;
            line-height:1.8;
        }

        /* FOOTER */

        footer{
            background:#0f172a;
            color:#cbd5e1;
            padding:80px 0 30px;
        }

        .footer-grid{
            display:grid;
            grid-template-columns:2fr 1fr 1fr 1fr;
            gap:40px;
            margin-bottom:60px;
        }

        .footer-logo{
            display:flex;
            align-items:center;
            gap:12px;
            color:white;
            font-weight:800;
            margin-bottom:20px;
            font-size:1.3rem;
        }

        .footer-about p{
            line-height:1.8;
            color:#94a3b8;
            margin-bottom:25px;
        }

        .socials{
            display:flex;
            gap:15px;
        }

        .socials a{
            width:42px;
            height:42px;
            border-radius:12px;
            background:#1e293b;
            display:flex;
            align-items:center;
            justify-content:center;
            color:white;
            transition:.3s;
        }

        .socials a:hover{
            background:var(--primary);
            transform:translateY(-3px);
        }

        .footer-links h4{
            color:white;
            margin-bottom:20px;
        }

        .footer-links a{
            display:block;
            color:#94a3b8;
            margin-bottom:12px;
            transition:.3s;
        }

        .footer-links a:hover{
            color:white;
            transform:translateX(4px);
        }

        .footer-bottom{
            border-top:1px solid rgba(255,255,255,.08);
            padding-top:30px;
            text-align:center;
            color:#64748b;
        }

        /* MOBILE */

        .menu-toggle{
            display:none;
            font-size:1.5rem;
            color:white;
            cursor:pointer;
        }

        .navbar.scrolled .menu-toggle{
            color:var(--dark);
        }

        .mobile-menu{
            display:none;
            position:absolute;
            top:100%;
            left:0;
            width:100%;
            background:white;
            padding:20px;
            box-shadow:0 10px 30px rgba(0,0,0,.1);
        }

        .mobile-menu.active{
            display:block;
        }

        .mobile-menu a{
            display:block;
            padding:12px 0;
            color:var(--dark);
            border-bottom:1px solid var(--border);
        }

        .mobile-menu a:last-child{
            border-bottom:none;
        }

        @media(max-width:992px){

            .hero-grid,
            .trust-wrapper,
            .footer-grid{
                grid-template-columns:1fr;
            }

            .feature-grid{
                grid-template-columns:1fr 1fr;
            }

            .steps{
                grid-template-columns:1fr 1fr;
            }

            .hero-text{
                text-align:center;
            }

            .hero-buttons,
            .hero-stats{
                justify-content:center;
            }
        }

        @media(max-width:768px){

            .nav-links,
            .nav-buttons{
                display:none;
            }

            .menu-toggle{
                display:block;
            }

            .hero-text h1{
                font-size:3rem;
            }

            .section-header h2{
                font-size:2.2rem;
            }

            .feature-grid,
            .steps,
            .loan-cards{
                grid-template-columns:1fr;
            }

            section{
                padding:80px 0;
            }

            .cta h2{
                font-size:2.2rem;
            }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar" id="navbar" role="navigation" aria-label="Main navigation">
    <div class="container nav-wrapper">

        <a href="{{ route('home') }}" class="logo" aria-label="QuickShare home">
            <div class="logo-icon">
                <i class="fa-solid fa-bolt" aria-hidden="true"></i>
            </div>
            QuickShare
        </a>

        <div class="nav-links">
            <a href="{{ route('home') }}#features">Features</a>
            <a href="{{ route('how-it-works') }}">How It Works</a>
            <a href="{{ route('home') }}#trust">Trust & Security</a>
            <a href="{{ route('faq') }}">FAQ</a>
            <a href="{{ route('contact') }}">Contact</a>
        </div>

        <div class="nav-buttons">

            @auth
            <a href="{{ route('dashboard') }}" class="btn btn-primary">
                Dashboard
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
            @else
            <a href="{{ route('login') }}" class="btn btn-outline">Login</a>
            <a href="{{ route('register') }}" class="btn btn-primary">
                Get Started
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
            @endauth
        </div>

        <div class="menu-toggle" id="menuToggle" role="button" aria-label="Toggle menu" aria-expanded="false" aria-controls="mobileMenu" tabindex="0">
            <i class="fa-solid fa-bars" aria-hidden="true"></i>
        </div>

        <div class="mobile-menu" id="mobileMenu">
            <a href="{{ route('home') }}#features">Features</a>
            <a href="{{ route('how-it-works') }}">How It Works</a>
            <a href="{{ route('home') }}#trust">Trust & Security</a>
            <a href="{{ route('faq') }}">FAQ</a>
            <a href="{{ route('contact') }}">Contact</a>

            @auth
            <a href="{{ route('dashboard') }}" style="color:var(--primary);font-weight:600;">Dashboard</a>
            @else
            <a href="{{ route('login') }}" style="color:var(--primary);font-weight:600;">Login</a>
            <a href="{{ route('register') }}" style="color:var(--primary);font-weight:600;">Get Started</a>
            @endauth
        </div>

    </div>
</nav>

<!-- HERO -->
<section class="hero">

    <div class="container hero-grid">

        <div class="hero-text">

            <h1>
                The Smart Way To
                <span>Borrow & Lend</span>
                In Namibia
            </h1>

            <p>
                QuickShare is a Namibian peer-to-peer lending platform that connects
                borrowers directly with lenders. Borrow money quickly with transparent
                rates, or fund verified borrowers and earn returns — all backed by
                KYC verification and a trust score system.
            </p>

            <div class="hero-buttons">
                <a href="{{ route('register') }}" class="btn btn-primary">
                    Become a Borrower
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>

                <a href="{{ route('register') }}" class="btn btn-outline">
                    <i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i>
                    Become a Lender
                </a>
            </div>

            <div class="hero-stats">

                <div class="stat">
                    <h3>{{ $stats['total_funded_formatted'] }}</h3>
                    <p>Total Funded</p>
                </div>

                <div class="stat">
                    <h3>{{ number_format($stats['verified_users']) }}</h3>
                    <p>Verified Users</p>
                </div>

                <div class="stat">
                    <h3>{{ $stats['repayment_rate'] > 0 ? $stats['repayment_rate'] . '%' : '—' }}</h3>
                    <p>Repayment Rate</p>
                </div>

            </div>

        </div>

        <div class="dashboard">

            <div class="dashboard-top">
                <div style="color:white;font-weight:700;">
                    Lender Dashboard
                </div>

                <div style="color:#94a3b8;">
                    Portfolio Overview
                </div>
            </div>

            <div class="balance-card">
                <small>Total Portfolio Value</small>
                <h2>{{ config('loan.general.currency_symbol', 'N$') }} {{ number_format($stats['total_funded_amount'], 2, '.', ',') }}</h2>

                <div style="display:flex;justify-content:space-between;">
                    <span>Lender Account</span>
                    <span>{{ $stats['active_lenders'] }} Active Lenders</span>
                </div>
            </div>

            <div class="loan-cards">

                <div class="loan-card">
                    <h4>Loans Funded</h4>

                    <div class="progress">
                        <span style="width:{{ $stats['total_loans'] > 0 ? min(100, round(($stats['loans_funded'] / $stats['total_loans']) * 100)) : 0 }}%;"></span>
                    </div>

                    <strong>{{ $stats['loans_funded'] }} / {{ $stats['total_loans'] }}</strong>
                </div>

                <div class="loan-card">
                    <h4>Avg Trust Score</h4>

                    <div class="progress">
                        <span style="width:{{ min(100, round($stats['average_trust_score'])) }}%;background:linear-gradient(135deg,#14b8a6,#10b981);"></span>
                    </div>

                    <strong>{{ number_format($stats['average_trust_score'], 1) }} / 100</strong>
                </div>

            </div>

        </div>

    </div>

</section>

<!-- FEATURES -->
<section class="features" id="features">

    <div class="container">

        <div class="section-header">
            <span>Why QuickShare</span>
            <h2>Built For Modern Namibian Finance</h2>
            <p>
                A peer-to-peer lending platform designed for Namibians — connecting
                borrowers and lenders directly with transparent rates, KYC verification,
                and a trust score system.
            </p>
        </div>

        <div class="feature-grid">

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>

                <h3>Secure & Verified</h3>

                <p>
                    KYC verification, secure encryption, fraud prevention,
                    and borrower trust scoring to create a safer lending environment.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fa-solid fa-bolt"></i>
                </div>

                <h3>Fast Loan Requests</h3>

                <p>
                    Apply for loans within minutes using a smooth digital
                    process optimized for mobile and desktop users in Namibia.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fa-solid fa-chart-line"></i>
                </div>

                <h3>Earn Competitive Returns</h3>

                <p>
                    Lenders can fund multiple verified borrowers across the marketplace
                    and earn returns directly from repayments.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fa-solid fa-wallet"></i>
                </div>

                <h3>Integrated & Digital P2P</h3>

                <p>
                    Borrow, repay, and manage your lending portfolio
                    from a centralized QuickShare platform experience.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fa-solid fa-users"></i>
                </div>

                <h3>Trust Score System</h3>

                <p>
                    Repayment history, KYC verification, and referral credibility
                    build your trust score — unlocking better rates and higher limits.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fa-solid fa-mobile-screen"></i>
                </div>

                <h3>Namibian Platform</h3>

                <p>
                    Built specifically for Namibians — with local currency (N$),
                    national ID verification, and a marketplace tailored to local needs.
                </p>
            </div>

        </div>

    </div>

</section>

<!-- HOW IT WORKS -->
<section class="how" id="how">

    <div class="container">

        <div class="section-header">
            <span>How It Works</span>
            <h2>Simple. Fast. Transparent.</h2>
            <p>
                QuickShare makes borrowing and lending easy with a transparent digital marketplace.
            </p>
        </div>

        <div class="steps">

            <div class="step">
                <div class="step-number">1</div>
                <h4>Create Account</h4>
                <p>
                    Register and complete KYC verification to unlock your QuickShare profile.
                </p>
            </div>

            <div class="step">
                <div class="step-number">2</div>
                <h4>Request or Browse</h4>
                <p>
                    Borrowers request loans. Lenders browse the marketplace and choose loans to fund.
                </p>
            </div>

            <div class="step">
                <div class="step-number">3</div>
                <h4>Get Funded</h4>
                <p>
                    Once a loan is fully funded, it is disbursed to the borrower. Lenders track their portfolio.
                </p>
            </div>

            <div class="step">
                <div class="step-number">4</div>
                <h4>Repay & Earn</h4>
                <p>
                    Borrowers repay on schedule and build their trust score. Lenders receive repayments with returns.
                </p>
            </div>

        </div>

    </div>

</section>

<!-- TRUST -->
<section class="trust" id="trust">

    <div class="container">

        <div class="trust-wrapper">

            <div>

                <div class="section-header" style="text-align:left;margin-bottom:30px;">
                    <span>Trust & Security</span>
                    <h2>Your Financial Safety Matters</h2>
                    <p>
                        QuickShare is designed with modern fintech security standards,
                        creating confidence for both borrowers and lenders.
                    </p>
                </div>

                <ul class="trust-list">
                    <li>
                        <i class="fa-solid fa-circle-check"></i>
                        256-bit SSL encryption & secure authentication
                    </li>

                    <li>
                        <i class="fa-solid fa-circle-check"></i>
                        KYC verification & borrower identity checks
                    </li>

                    <li>
                        <i class="fa-solid fa-circle-check"></i>
                        Transparent repayment tracking
                    </li>

                    <li>
                        <i class="fa-solid fa-circle-check"></i>
                        Automated trust score system
                    </li>

                    <li>
                        <i class="fa-solid fa-circle-check"></i>
                        Manual loan review by platform administrators
                    </li>

                    <li>
                        <i class="fa-solid fa-circle-check"></i>
                        Lenders can fund multiple borrowers to spread risk
                    </li>
                </ul>

            </div>

            <div class="trust-card">

                <h2 style="font-size:2.2rem;margin-bottom:20px;">
                    Trust Score Tiers
                </h2>

                <div style="margin-top:30px;display:grid;gap:20px;">

                    <div style="background:rgba(255,255,255,.05);padding:20px;border-radius:18px;">
                        <strong style="color:#c084fc;">Bronze</strong>
                        <p style="margin-top:10px;color:#cbd5e1;">
                            Starter access and entry-level borrowing.
                        </p>
                    </div>

                    <div style="background:rgba(255,255,255,.05);padding:20px;border-radius:18px;">
                        <strong style="color:#d1d5db;">Silver</strong>
                        <p style="margin-top:10px;color:#cbd5e1;">
                            Better rates and increased borrowing limits.
                        </p>
                    </div>

                    <div style="background:rgba(255,255,255,.05);padding:20px;border-radius:18px;">
                        <strong style="color:#facc15;">Gold</strong>
                        <p style="margin-top:10px;color:#cbd5e1;">
                            Preferred rates with improved funding visibility.
                        </p>
                    </div>

                    <div style="background:rgba(255,255,255,.05);padding:20px;border-radius:18px;">
                        <strong style="color:#22d3ee;">Platinum</strong>
                        <p style="margin-top:10px;color:#cbd5e1;">
                            Premium access, top funding priority, and lowest rates.
                        </p>
                    </div>

                </div>

            </div>

        </div>

    </div>

</section>

<!-- FAQ -->
<section class="faq" id="faq">

    <div class="container">

        <div class="section-header">
            <span>Frequently Asked Questions</span>
            <h2>Everything You Need To Know</h2>
        </div>

        <div class="faq-wrapper">

            <div class="faq-item active">
                <div class="faq-question" role="button" aria-expanded="true" tabindex="0">
                    What is QuickShare?
                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </div>

                <div class="faq-answer">
                    <p>
                        QuickShare is a Namibian peer-to-peer lending platform that connects borrowers directly with lenders. Borrowers request loans, lenders fund them, and repayments are tracked transparently.
                    </p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" role="button" aria-expanded="false" tabindex="0">
                    How quickly can I get funded?
                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </div>

                <div class="faq-answer">
                    <p>
                        Funding times depend on your trust score, verification status, and lender interest in your loan. Loans are reviewed by administrators before being listed on the marketplace.
                    </p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" role="button" aria-expanded="false" tabindex="0">
                    Is QuickShare secure?
                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </div>

                <div class="faq-answer">
                    <p>
                        Yes. We use SSL encryption, KYC identity verification, and fraud monitoring to protect users and transactions. Sensitive data is encrypted at rest.
                    </p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" role="button" aria-expanded="false" tabindex="0">
                    Can lenders fund multiple borrowers?
                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </div>

                <div class="faq-answer">
                    <p>
                        Yes. Lenders can browse the marketplace and fund multiple borrowers to spread risk across different loans and trust score tiers.
                    </p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" role="button" aria-expanded="false" tabindex="0">
                    What happens if a borrower defaults?
                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </div>

                <div class="faq-answer">
                    <p>
                        Our collections process works to recover funds. The borrower's trust score is negatively impacted, and overdue repayments are tracked. Lending carries risk and returns are not guaranteed.
                    </p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" role="button" aria-expanded="false" tabindex="0">
                    How does the trust score work?
                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </div>

                <div class="faq-answer">
                    <p>
                        Your trust score is based on repayment history, KYC verification, account age, and referral credibility. Higher scores unlock better rates and higher loan limits across Bronze, Silver, Gold, and Platinum tiers.
                    </p>
                </div>
            </div>

        </div>

    </div>

</section>

<!-- CTA -->
<section class="cta">

    <div class="container">

        <h2>
            Start Your QuickShare Journey Today
        </h2>

        <p>
            Whether you need to borrow money or want to lend to verified Namibian borrowers,
            QuickShare gives you a secure and transparent peer-to-peer lending experience.
        </p>

        <div class="hero-buttons" style="justify-content:center;">
            <a href="{{ route('register') }}" class="btn btn-primary">
                Become a Borrower
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>

            <a href="{{ route('register') }}" class="btn btn-outline">
                <i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i>
                Become a Lender
            </a>
        </div>

    </div>

</section>

<!-- FOOTER -->
<footer id="contact">

    <div class="container">

        <div class="footer-grid">

            <div class="footer-about">

                <div class="footer-logo">
                    <div class="logo-icon">
                        <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                    </div>
                    QuickShare
                </div>

                <p>
                    A Namibian peer-to-peer lending platform connecting borrowers and lenders
                    with trust, transparency, and technology.
                </p>

                <div class="socials">
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f" aria-hidden="true"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram" aria-hidden="true"></i></a>
                    <a href="#" aria-label="X (Twitter)"><i class="fab fa-x-twitter" aria-hidden="true"></i></a>
                    <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in" aria-hidden="true"></i></a>
                </div>

            </div>

            <div class="footer-links">
                <h4>Platform</h4>
                <a href="{{ route('how-it-works') }}">How It Works</a>
                <a href="{{ route('borrow') }}">Borrow</a>
                <a href="{{ route('lend') }}">Lend</a>
                <a href="{{ route('home') }}#trust">Trust Score</a>
            </div>

            <div class="footer-links">
                <h4>Company</h4>
                <a href="{{ route('about') }}">About Us</a>
                <a href="{{ route('contact') }}">Contact</a>
                <a href="{{ route('faq') }}">FAQ</a>
                <a href="{{ route('support') }}">Support</a>
            </div>

            <div class="footer-links">
                <h4>Legal</h4>
                <a href="{{ route('privacy') }}">Privacy Policy</a>
                <a href="{{ route('terms') }}">Terms of Service</a>
                <a href="{{ route('compliance') }}">Compliance</a>
                <a href="{{ route('support') }}">Help Center</a>
            </div>

        </div>

        <div class="footer-bottom">
            © {{ date('Y') }} QuickShare Namibia. All Rights Reserved.
        </div>

    </div>

</footer>

<script>

    // Navbar Scroll
    const navbar = document.getElementById('navbar');

    window.addEventListener('scroll', () => {
        if(window.scrollY > 50){
            navbar.classList.add('scrolled');
        }else{
            navbar.classList.remove('scrolled');
        }
    });

    // Mobile Menu Toggle
    const menuToggle = document.getElementById('menuToggle');
    const mobileMenu = document.getElementById('mobileMenu');

    if(menuToggle && mobileMenu){
        const toggleMenu = () => {
            const isActive = mobileMenu.classList.toggle('active');
            menuToggle.setAttribute('aria-expanded', isActive ? 'true' : 'false');
        };

        menuToggle.addEventListener('click', toggleMenu);
        menuToggle.addEventListener('keydown', (e) => {
            if(e.key === 'Enter' || e.key === ' '){
                e.preventDefault();
                toggleMenu();
            }
        });

        // Close menu when clicking on a link
        mobileMenu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                mobileMenu.classList.remove('active');
                menuToggle.setAttribute('aria-expanded', 'false');
            });
        });
    }

    // FAQ Accordion
    const faqItems = document.querySelectorAll('.faq-item');

    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question');

        const toggleFaq = () => {
            const isActive = item.classList.toggle('active');
            question.setAttribute('aria-expanded', isActive ? 'true' : 'false');
        };

        question.addEventListener('click', toggleFaq);
        question.addEventListener('keydown', (e) => {
            if(e.key === 'Enter' || e.key === ' '){
                e.preventDefault();
                toggleFaq();
            }
        });
    });

</script>

</body>
</html>