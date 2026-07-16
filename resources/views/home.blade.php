<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>QuickShare - Smart Peer-to-Peer Lending for Namibia</title>

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
<nav class="navbar" id="navbar">
    <div class="container nav-wrapper">

        <a href="/" class="logo">
            <div class="logo-icon">
                <i class="fa-solid fa-bolt"></i>
            </div>
            QuickShare
        </a>

        <div class="nav-links">
            <a href="#features">Features</a>
            <a href="#how">How It Works</a>
            <a href="#trust">Trust & Security</a>
            <a href="#faq">FAQ</a>
            <a href="#contact">Contact</a>
        </div>

        <div class="nav-buttons">

            @auth
            <a href="/register" class="btn btn-primary">
                Dashboard
                <i class="fa-solid fa-arrow-right"></i>
            </a>
            @else
            <a href="/login" class="btn btn-outline">Login</a>
            <a href="/register" class="btn btn-primary">
                Get Started
                <i class="fa-solid fa-arrow-right"></i>
            </a>
            @endauth
        </div>

        <div class="menu-toggle" id="menuToggle">
            <i class="fa-solid fa-bars"></i>
        </div>

        <div class="mobile-menu" id="mobileMenu">
            <a href="#features">Features</a>
            <a href="#how">How It Works</a>
            <a href="#trust">Trust & Security</a>
            <a href="#faq">FAQ</a>
            <a href="#contact">Contact</a>

            @auth
            <a href="/register" style="color:var(--primary);font-weight:600;">Dashboard</a>
            @else
            <a href="/login" style="color:var(--primary);font-weight:600;">Login</a>
            <a href="/register" style="color:var(--primary);font-weight:600;">Get Started</a>
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
                <span>Borrow & Invest</span>
                In Namibia
            </h1>

            <p>
                QuickShare is a modern peer-to-peer lending platform built for Namibians.
                Borrow money quickly, fund verified borrowers securely, and grow your
                financial opportunities with a trusted digital ecosystem.
            </p>

            <div class="hero-buttons">
                <a href="/register" class="btn btn-primary">
                    Create Account
                    <i class="fa-solid fa-arrow-right"></i>
                </a>

                <a href="#how" class="btn btn-outline">
                    <i class="fa-solid fa-circle-play"></i>
                    How It Works
                </a>
            </div>

            <div class="hero-stats">

                <div class="stat">
                    <h3>N$2.4M+</h3>
                    <p>Loans Funded</p>
                </div>

                <div class="stat">
                    <h3>3,500+</h3>
                    <p>Verified Users</p>
                </div>

                <div class="stat">
                    <h3>96%</h3>
                    <p>Repayment Rate</p>
                </div>

            </div>

        </div>

        <div class="dashboard">

            <div class="dashboard-top">
                <div style="color:white;font-weight:700;">
                    QuickShare Wallet
                </div>

                <div style="color:#94a3b8;">
                    Live Dashboard
                </div>
            </div>

            <div class="balance-card">
                <small>Available Balance</small>
                <h2>N$ 45,280</h2>

                <div style="display:flex;justify-content:space-between;">
                    <span>Investor Account</span>
                    <span>+18.2%</span>
                </div>
            </div>

            <div class="loan-cards">

                <div class="loan-card">
                    <h4>Loan Funding</h4>

                    <div class="progress">
                        <span style="width:72%;"></span>
                    </div>

                    <strong>N$72,000 / N$100,000</strong>
                </div>

                <div class="loan-card">
                    <h4>Trust Score</h4>

                    <div class="progress">
                        <span style="width:91%;background:linear-gradient(135deg,#14b8a6,#10b981);"></span>
                    </div>

                    <strong>91% Platinum</strong>
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
            <h2>Built For Modern African Finance</h2>
            <p>
                Designed with the feel of a premium fintech platform while blending perfectly
                with modern admin systems like Ample Admin for a seamless user experience.
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
                    Lenders can diversify investments across verified borrowers
                    and earn attractive returns directly from repayments.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fa-solid fa-wallet"></i>
                </div>

                <h3>Integrated Digital Wallet</h3>

                <p>
                    Deposit, withdraw, repay, and manage investments
                    from a centralized QuickShare wallet experience.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fa-solid fa-users"></i>
                </div>

                <h3>Community Trust System</h3>

                <p>
                    Referral credibility and repayment behavior help
                    build a stronger financial community ecosystem.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fa-solid fa-mobile-screen"></i>
                </div>

                <h3>Mobile Friendly</h3>

                <p>
                    Fully responsive and optimized for mobile-first usage,
                    perfect for Namibian users on the go.
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
                QuickShare makes borrowing and investing easy with a modern digital workflow.
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
                <h4>Request or Fund</h4>
                <p>
                    Borrowers request loans while lenders browse opportunities.
                </p>
            </div>

            <div class="step">
                <div class="step-number">3</div>
                <h4>Smart Matching</h4>
                <p>
                    Our system intelligently matches trusted borrowers and investors.
                </p>
            </div>

            <div class="step">
                <div class="step-number">4</div>
                <h4>Grow Your Score</h4>
                <p>
                    Repay on time and unlock better limits, rates, and platform benefits.
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
                        Advanced KYC and borrower verification
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
                        Diversified lender investment model
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
                <div class="faq-question">
                    What is QuickShare?
                    <i class="fa-solid fa-chevron-down"></i>
                </div>

                <div class="faq-answer">
                    <p>
                        QuickShare is a Namibian peer-to-peer lending platform connecting borrowers and lenders through a secure digital ecosystem.
                    </p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">
                    How quickly can I get funded?
                    <i class="fa-solid fa-chevron-down"></i>
                </div>

                <div class="faq-answer">
                    <p>
                        Funding times depend on your trust score, verification status, and lender availability. Many requests can be funded within hours.
                    </p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">
                    Is QuickShare secure?
                    <i class="fa-solid fa-chevron-down"></i>
                </div>

                <div class="faq-answer">
                    <p>
                        Yes. We use secure encryption, identity verification, and fraud monitoring systems to protect users and transactions.
                    </p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question">
                    Can lenders fund multiple borrowers?
                    <i class="fa-solid fa-chevron-down"></i>
                </div>

                <div class="faq-answer">
                    <p>
                        Absolutely. Diversifying investments across multiple borrowers helps reduce risk exposure.
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
            Ready To Join The Financial Future?
        </h2>

        <p>
            Whether you need quick funding or want to grow your money through smart lending,
            QuickShare gives Namibians a secure and modern fintech experience.
        </p>

        <a href="/register" class="btn btn-primary">
            Get Started Today
            <i class="fa-solid fa-arrow-right"></i>
        </a>

    </div>

</section>

<!-- FOOTER -->
<footer id="contact">

    <div class="container">

        <div class="footer-grid">

            <div class="footer-about">

                <div class="footer-logo">
                    <div class="logo-icon">
                        <i class="fa-solid fa-bolt"></i>
                    </div>
                    QuickShare
                </div>

                <p>
                    A modern Namibian fintech platform transforming peer-to-peer lending
                    with trust, transparency, and technology.
                </p>

                <div class="socials">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-x-twitter"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                </div>

            </div>

            <div class="footer-links">
                <h4>Platform</h4>
                <a href="#">How It Works</a>
                <a href="#">Trust Score</a>
                <a href="#">Security</a>
                <a href="#">Contact Us</a>
            </div>

            <div class="footer-links">
                <h4>Company</h4>
                <a href="#">About Us</a>
                <a href="#">Careers</a>
                <a href="#">Blog</a>
                <a href="#">Contact</a>
            </div>

            <div class="footer-links">
                <h4>Legal</h4>
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
                <a href="#">Compliance</a>
                <a href="#">Support</a>
            </div>

        </div>

        <div class="footer-bottom">
            © 2026 QuickShare Namibia. All Rights Reserved.
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
        menuToggle.addEventListener('click', () => {
            mobileMenu.classList.toggle('active');
        });

        // Close menu when clicking on a link
        mobileMenu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                mobileMenu.classList.remove('active');
            });
        });
    }

    // FAQ Accordion
    const faqItems = document.querySelectorAll('.faq-item');

    faqItems.forEach(item => {
        item.querySelector('.faq-question').addEventListener('click', () => {

            item.classList.toggle('active');

        });
    });

</script>

</body>
</html>