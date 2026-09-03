@extends('layouts.public')

@section('title', 'Lend')
@section('description', 'Lend to verified Namibian borrowers on QuickShare. Browse the marketplace, fund loans, and earn returns through a transparent peer-to-peer lending platform.')

@section('content')
<!-- PAGE HERO -->
<section class="page-hero">
    <div class="container">
        <h1>Lend on QuickShare</h1>
        <p>
            Browse verified borrower loan requests on the marketplace, fund loans that match
            your risk appetite, and earn returns from repayments. Lending carries risk —
            returns are not guaranteed.
        </p>
    </div>
</section>

<!-- HOW LENDING WORKS -->
<section style="background:white;">
    <div class="container">
        <div class="section-header">
            <span>How Lending Works</span>
            <h2>Four Simple Steps</h2>
            <p>From registration to earning returns — a transparent process for Namibian lenders.</p>
        </div>
        <div class="steps">
            <div class="step">
                <div class="step-number">1</div>
                <h4>Create Account</h4>
                <p>Register and complete KYC verification to activate your lender profile.</p>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <h4>Browse Marketplace</h4>
                <p>View approved loan requests with borrower trust scores, amounts, and expected returns.</p>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <h4>Fund Loans</h4>
                <p>Choose loans that match your risk appetite. Fund from N${{ number_format(config('loan.marketplace.min_funding_amount'), 0) }} per loan.</p>
            </div>
            <div class="step">
                <div class="step-number">4</div>
                <h4>Earn Returns</h4>
                <p>Receive repayments with interest as borrowers repay. Track your portfolio in your dashboard.</p>
            </div>
        </div>
    </div>
</section>

<!-- LENDER DETAILS -->
<section style="background:linear-gradient(to bottom,#f8fafc,#eef4ff);">
    <div class="container">
        <div class="section-header">
            <span>Lender Information</span>
            <h2>What You Need to Know</h2>
        </div>
        <div class="feature-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i></div>
                <h3>Minimum Funding</h3>
                <p>Fund loans from N${{ number_format(config('loan.marketplace.min_funding_amount'), 0) }} per loan. This allows you to spread your funds across multiple borrowers.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-chart-pie" aria-hidden="true"></i></div>
                <h3>Diversify Your Portfolio</h3>
                <p>Fund multiple loans across different trust score tiers to spread risk. Diversification helps reduce the impact of any single borrower defaulting.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-percent" aria-hidden="true"></i></div>
                <h3>Returns Based on Risk</h3>
                <p>Lender returns range from {{ config('loan.trust_tiers.platinum.lender_return_percent') }}% to {{ config('loan.trust_tiers.bronze.lender_return_percent') }}% depending on the borrower's trust score tier. Higher risk loans offer higher potential returns.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></div>
                <h3>Verified Borrowers</h3>
                <p>All borrowers complete KYC verification with a valid Namibian ID. Loans are reviewed by administrators before being listed on the marketplace.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></div>
                <h3>Risk Disclosure</h3>
                <p>Lending carries risk. Borrowers may default, and returns are not guaranteed. We mitigate risk through trust scores, KYC, and a collections process.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-wallet" aria-hidden="true"></i></div>
                <h3>Track Everything</h3>
                <p>Monitor your funding transactions, investments, earnings, and repayment schedules from your lender dashboard.</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta">
    <div class="container">
        <h2>Ready to Start Lending?</h2>
        <p>Create your QuickShare account, complete KYC verification, and start funding verified Namibian borrowers.</p>
        <div class="hero-buttons" style="justify-content:center;">
            <a href="{{ route('register') }}" class="btn btn-primary">
                Become a Lender
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
            <a href="{{ route('how-it-works') }}" class="btn btn-outline">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                Learn More
            </a>
        </div>
    </div>
</section>
@endsection
