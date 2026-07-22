<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>@yield('title', 'QuickShare') — QuickShare Namibia</title>
    <meta name="description" content="@yield('description', 'QuickShare is a Namibian peer-to-peer lending platform connecting borrowers and lenders with trust, transparency, and technology.')" />
    <meta name="robots" content="index, follow" />
    <link rel="canonical" href="{{ url()->current() }}" />

    <!-- OpenGraph -->
    <meta property="og:type" content="website" />
    <meta property="og:title" content="@yield('og_title', 'QuickShare — Peer-to-Peer Lending for Namibia')" />
    <meta property="og:description" content="@yield('og_description', 'A Namibian peer-to-peer lending platform connecting borrowers and lenders.')" />
    <meta property="og:url" content="{{ url()->current() }}" />
    <meta property="og:site_name" content="QuickShare" />
    <meta property="og:locale" content="en_NA" />

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="@yield('og_title', 'QuickShare — Peer-to-Peer Lending for Namibia')" />
    <meta name="twitter:description" content="@yield('og_description', 'A Namibian peer-to-peer lending platform connecting borrowers and lenders.')" />

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
        *{margin:0;padding:0;box-sizing:border-box;}
        html{scroll-behavior:smooth;}
        body{font-family:'Inter',sans-serif;background:#f4f7fb;color:var(--dark);overflow-x:hidden;}
        a{text-decoration:none;}
        .container{width:min(1200px,90%);margin:auto;}

        /* NAVBAR */
        .navbar{position:fixed;top:0;left:0;width:100%;z-index:999;transition:.3s ease;padding:18px 0;}
        .navbar.scrolled{background:rgba(255,255,255,.95);backdrop-filter:blur(16px);box-shadow:0 5px 25px rgba(0,0,0,.05);}
        .nav-wrapper{display:flex;justify-content:space-between;align-items:center;}
        .logo{display:flex;align-items:center;gap:12px;color:white;font-weight:800;font-size:1.4rem;}
        .navbar.scrolled .logo{color:var(--dark);}
        .logo-icon{width:45px;height:45px;border-radius:14px;background:linear-gradient(135deg,var(--primary),var(--secondary));display:flex;align-items:center;justify-content:center;color:white;font-size:1.2rem;box-shadow:0 10px 20px rgba(99,91,255,.25);}
        .nav-links{display:flex;align-items:center;gap:35px;}
        .nav-links a{color:white;font-weight:500;transition:.3s;}
        .navbar.scrolled .nav-links a{color:var(--dark);}
        .nav-links a:hover{color:var(--secondary);}
        .nav-buttons{display:flex;gap:15px;}
        .btn{padding:14px 26px;border-radius:14px;font-weight:600;transition:.3s ease;display:inline-flex;align-items:center;gap:10px;border:none;cursor:pointer;}
        .btn-primary{background:linear-gradient(135deg,var(--primary),var(--accent));color:white;box-shadow:0 10px 25px rgba(99,91,255,.35);}
        .btn-primary:hover{transform:translateY(-3px);}
        .btn-outline{border:1px solid rgba(255,255,255,.3);color:white;background:transparent;}
        .navbar.scrolled .btn-outline{border:1px solid var(--border);color:var(--dark);}
        .btn-outline:hover{background:white;color:var(--primary);}

        /* PAGE HERO */
        .page-hero{
            background:
                radial-gradient(circle at top right, rgba(20,184,166,.25), transparent 30%),
                radial-gradient(circle at bottom left, rgba(99,91,255,.35), transparent 30%),
                linear-gradient(135deg,#0f172a,#111827,#1e1b4b);
            padding:160px 0 80px;
            position:relative;
            overflow:hidden;
        }
        .page-hero h1{color:white;font-size:3.5rem;font-weight:900;margin-bottom:20px;line-height:1.1;}
        .page-hero p{color:#cbd5e1;font-size:1.15rem;line-height:1.8;max-width:700px;}

        /* SECTIONS */
        section{padding:100px 0;}
        .section-header{text-align:center;margin-bottom:70px;}
        .section-header span{color:var(--primary);font-weight:700;text-transform:uppercase;letter-spacing:1px;font-size:.85rem;}
        .section-header h2{font-size:3rem;margin:18px 0;}
        .section-header p{max-width:750px;margin:auto;color:var(--muted);line-height:1.8;font-size:1.05rem;}

        /* CONTENT CARDS */
        .content-card{
            background:white;
            border-radius:var(--radius-xl);
            padding:40px;
            box-shadow:var(--shadow-md);
            margin-bottom:30px;
        }
        .content-card h3{font-size:1.5rem;margin-bottom:15px;}
        .content-card p{color:var(--muted);line-height:1.8;margin-bottom:15px;}
        .content-card ul{list-style:none;padding:0;}
        .content-card ul li{padding:10px 0;color:var(--muted);display:flex;align-items:flex-start;gap:12px;line-height:1.7;}
        .content-card ul li i{color:var(--success);margin-top:4px;}

        /* FEATURE GRID */
        .feature-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:30px;}
        .feature-card{background:#fff;padding:35px;border-radius:var(--radius-xl);border:1px solid #edf2f7;transition:.35s ease;}
        .feature-card:hover{transform:translateY(-8px);box-shadow:var(--shadow-lg);}
        .feature-icon{width:70px;height:70px;border-radius:20px;background:linear-gradient(135deg,var(--primary),var(--accent));color:white;display:flex;align-items:center;justify-content:center;font-size:1.5rem;margin-bottom:25px;}
        .feature-card h3{margin-bottom:15px;font-size:1.35rem;}
        .feature-card p{color:var(--muted);line-height:1.8;}

        /* STEPS */
        .steps{display:grid;grid-template-columns:repeat(4,1fr);gap:25px;}
        .step{background:white;padding:35px 25px;border-radius:var(--radius-xl);text-align:center;box-shadow:var(--shadow-md);}
        .step-number{width:55px;height:55px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));color:white;display:flex;align-items:center;justify-content:center;font-weight:800;margin:0 auto 20px;font-size:1.1rem;}
        .step h4{margin-bottom:14px;}
        .step p{color:var(--muted);line-height:1.7;}

        /* CTA */
        .cta{background:radial-gradient(circle at top left, rgba(255,255,255,.08), transparent 25%),linear-gradient(135deg,var(--primary),#4338ca,#0f172a);color:white;text-align:center;}
        .cta h2{font-size:3rem;margin-bottom:20px;}
        .cta p{max-width:700px;margin:0 auto 40px;color:#dbeafe;line-height:1.8;}
        .hero-buttons{display:flex;gap:18px;flex-wrap:wrap;}

        /* FOOTER */
        footer{background:#0f172a;color:#cbd5e1;padding:80px 0 30px;}
        .footer-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:40px;margin-bottom:60px;}
        .footer-logo{display:flex;align-items:center;gap:12px;color:white;font-weight:800;margin-bottom:20px;font-size:1.3rem;}
        .footer-about p{line-height:1.8;color:#94a3b8;margin-bottom:25px;}
        .socials{display:flex;gap:15px;}
        .socials a{width:42px;height:42px;border-radius:12px;background:#1e293b;display:flex;align-items:center;justify-content:center;color:white;transition:.3s;}
        .socials a:hover{background:var(--primary);transform:translateY(-3px);}
        .footer-links h4{color:white;margin-bottom:20px;}
        .footer-links a{display:block;color:#94a3b8;margin-bottom:12px;transition:.3s;}
        .footer-links a:hover{color:white;transform:translateX(4px);}
        .footer-bottom{border-top:1px solid rgba(255,255,255,.08);padding-top:30px;text-align:center;color:#64748b;}

        /* MOBILE */
        .menu-toggle{display:none;font-size:1.5rem;color:white;cursor:pointer;}
        .navbar.scrolled .menu-toggle{color:var(--dark);}
        .mobile-menu{display:none;position:absolute;top:100%;left:0;width:100%;background:white;padding:20px;box-shadow:0 10px 30px rgba(0,0,0,.1);}
        .mobile-menu.active{display:block;}
        .mobile-menu a{display:block;padding:12px 0;color:var(--dark);border-bottom:1px solid var(--border);}
        .mobile-menu a:last-child{border-bottom:none;}

        @media(max-width:992px){
            .footer-grid{grid-template-columns:1fr;}
            .feature-grid{grid-template-columns:1fr 1fr;}
            .steps{grid-template-columns:1fr 1fr;}
        }
        @media(max-width:768px){
            .nav-links,.nav-buttons{display:none;}
            .menu-toggle{display:block;}
            .page-hero h1{font-size:2.5rem;}
            .section-header h2{font-size:2.2rem;}
            .feature-grid,.steps{grid-template-columns:1fr;}
            section{padding:80px 0;}
            .cta h2{font-size:2.2rem;}
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar scrolled" id="navbar" role="navigation" aria-label="Main navigation">
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

@yield('content')

<!-- FOOTER -->
<footer>
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
    const navbar = document.getElementById('navbar');
    window.addEventListener('scroll', () => {
        if(window.scrollY > 50){
            navbar.classList.add('scrolled');
        }else{
            navbar.classList.remove('scrolled');
        }
    });

    const menuToggle = document.getElementById('menuToggle');
    const mobileMenu = document.getElementById('mobileMenu');
    if(menuToggle && mobileMenu){
        const toggleMenu = () => {
            const isActive = mobileMenu.classList.toggle('active');
            menuToggle.setAttribute('aria-expanded', isActive ? 'true' : 'false');
        };
        menuToggle.addEventListener('click', toggleMenu);
        menuToggle.addEventListener('keydown', (e) => {
            if(e.key === 'Enter' || e.key === ' '){ e.preventDefault(); toggleMenu(); }
        });
        mobileMenu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                mobileMenu.classList.remove('active');
                menuToggle.setAttribute('aria-expanded', 'false');
            });
        });
    }
</script>

@stack('scripts')
</body>
</html>
