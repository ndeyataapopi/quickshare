@extends('layouts.public')

@section('title', 'About Us')
@section('description', 'Learn about QuickShare — a Namibian peer-to-peer lending platform built to connect borrowers and lenders with trust, transparency, and technology.')

@section('content')
<!-- PAGE HERO -->
<section class="page-hero">
    <div class="container">
        <h1>About QuickShare</h1>
        <p>
            QuickShare is a Namibian peer-to-peer lending platform that connects borrowers
            directly with lenders — removing traditional barriers and offering transparent,
            trust-based lending.
        </p>
    </div>
</section>

<!-- MISSION -->
<section style="background:white;">
    <div class="container">
        <div class="section-header">
            <span>Our Mission</span>
            <h2>Building a Trusted Lending Community</h2>
            <p>
                We believe Namibians deserve access to fair, transparent, and modern lending.
                QuickShare connects people who need to borrow with people who want to lend —
                backed by KYC verification, a trust score system, and a transparent marketplace.
            </p>
        </div>
    </div>
</section>

<!-- VALUES -->
<section style="background:linear-gradient(to bottom,#f8fafc,#eef4ff);">
    <div class="container">
        <div class="section-header">
            <span>Our Values</span>
            <h2>What We Stand For</h2>
        </div>
        <div class="feature-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-handshake" aria-hidden="true"></i></div>
                <h3>Trust</h3>
                <p>Every user is KYC-verified. Every loan is reviewed. Trust scores reward responsible behaviour and create a safer platform for everyone.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-eye" aria-hidden="true"></i></div>
                <h3>Transparency</h3>
                <p>Interest rates, fees, and repayment terms are shown upfront. No hidden charges. Borrowers and lenders can track everything in their dashboards.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-flag" aria-hidden="true"></i></div>
                <h3>Namibian First</h3>
                <p>Built for Namibians, with local currency (N$), national ID verification, and a marketplace tailored to the needs of the local community.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></div>
                <h3>Security</h3>
                <p>SSL encryption, encrypted data at rest, fraud monitoring, and strict access controls protect our users and their information.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i></div>
                <h3>Responsible Lending</h3>
                <p>Loan limits, affordability assessments, and one-active-loan rules help ensure borrowers borrow within their means.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></div>
                <h3>Community</h3>
                <p>Referral credibility and repayment behaviour build a stronger financial community. Good actors are rewarded with better rates and limits.</p>
            </div>
        </div>
    </div>
</section>

<!-- HOW IT WORKS PREVIEW -->
<section style="background:white;">
    <div class="container">
        <div class="section-header">
            <span>How It Works</span>
            <h2>Borrowing and Lending on QuickShare</h2>
            <p>A transparent four-step process for both borrowers and lenders.</p>
        </div>
        <div class="steps">
            <div class="step">
                <div class="step-number">1</div>
                <h4>Create Account</h4>
                <p>Register and complete KYC verification with your Namibian ID.</p>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <h4>Request or Browse</h4>
                <p>Borrowers request loans. Lenders browse the marketplace.</p>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <h4>Get Funded</h4>
                <p>Approved loans are funded by lenders and disbursed to borrowers.</p>
            </div>
            <div class="step">
                <div class="step-number">4</div>
                <h4>Repay & Earn</h4>
                <p>Borrowers repay and build trust. Lenders receive returns.</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta">
    <div class="container">
        <h2>Join QuickShare Today</h2>
        <p>Whether you need to borrow or want to lend to verified Namibian borrowers, QuickShare gives you a secure and transparent platform.</p>
        <div class="hero-buttons" style="justify-content:center;">
            <a href="{{ route('register') }}" class="btn btn-primary">
                Get Started
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
            <a href="{{ route('how-it-works') }}" class="btn btn-outline">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                How It Works
            </a>
        </div>
    </div>
</section>
@endsection
