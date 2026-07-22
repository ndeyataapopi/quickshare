@extends('layouts.public')

@section('title', 'How It Works')
@section('description', 'Learn how QuickShare works — a transparent four-step process for borrowers and lenders on Namibia's peer-to-peer lending platform.')

@section('content')
<!-- PAGE HERO -->
<section class="page-hero">
    <div class="container">
        <h1>How QuickShare Works</h1>
        <p>
            A transparent four-step process for both borrowers and lenders —
            from registration to repayment.
        </p>
    </div>
</section>

<!-- FOR BORROWERS -->
<section style="background:white;">
    <div class="container">
        <div class="section-header">
            <span>For Borrowers</span>
            <h2>Borrowing on QuickShare</h2>
            <p>From registration to receiving funds — a transparent process designed for Namibian borrowers.</p>
        </div>
        <div class="steps">
            <div class="step">
                <div class="step-number">1</div>
                <h4>Create Account</h4>
                <p>Register with your Namibian ID and complete KYC verification to activate your profile.</p>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <h4>Request a Loan</h4>
                <p>Specify the amount and repayment period. Your interest rate is calculated based on your trust score.</p>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <h4>Get Reviewed</h4>
                <p>Administrators review your request. Approved loans are listed on the marketplace for lenders to fund.</p>
            </div>
            <div class="step">
                <div class="step-number">4</div>
                <h4>Receive & Repay</h4>
                <p>Once funded, the loan is disbursed. Repay on schedule to build your trust score for better rates.</p>
            </div>
        </div>
    </div>
</section>

<!-- FOR LENDERS -->
<section style="background:linear-gradient(to bottom,#f8fafc,#eef4ff);">
    <div class="container">
        <div class="section-header">
            <span>For Lenders</span>
            <h2>Lending on QuickShare</h2>
            <p>Browse the marketplace, fund verified borrowers, and earn returns from repayments.</p>
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

<!-- TRUST SCORE SYSTEM -->
<section style="background:white;">
    <div class="container">
        <div class="section-header">
            <span>Trust Score System</span>
            <h2>Build Your Score, Unlock Better Rates</h2>
            <p>
                Our trust score system ensures responsible borrowing and lending.
                Build your score through repayment history, KYC verification, and referral credibility
                to access better rates and higher loan limits.
            </p>
        </div>
        <div class="feature-grid">
            <div class="feature-card">
                <div class="feature-icon" style="background:linear-gradient(135deg,#c084fc,#a78bfa);"><i class="fa-solid fa-medal" aria-hidden="true"></i></div>
                <h3>Bronze</h3>
                <p>Score 0–49. Entry-level access with the highest rates. Maximum loan: N${{ number_format(config('loan.trust_tiers.bronze.maximum_loan'), 0) }}.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon" style="background:linear-gradient(135deg,#d1d5db,#9ca3af);"><i class="fa-solid fa-medal" aria-hidden="true"></i></div>
                <h3>Silver</h3>
                <p>Score 50–69. Standard rates with increased limits. Maximum loan: N${{ number_format(config('loan.trust_tiers.silver.maximum_loan'), 0) }}.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon" style="background:linear-gradient(135deg,#facc15,#eab308);"><i class="fa-solid fa-medal" aria-hidden="true"></i></div>
                <h3>Gold</h3>
                <p>Score 70–84. Preferred rates with improved funding visibility. Maximum loan: N${{ number_format(config('loan.trust_tiers.gold.maximum_loan'), 0) }}.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon" style="background:linear-gradient(135deg,#22d3ee,#06b6d4);"><i class="fa-solid fa-medal" aria-hidden="true"></i></div>
                <h3>Platinum</h3>
                <p>Score 85–100. Premium access, lowest rates, top funding priority. Maximum loan: N${{ number_format(config('loan.trust_tiers.platinum.maximum_loan'), 0) }}.</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta">
    <div class="container">
        <h2>Ready to Get Started?</h2>
        <p>Whether you need to borrow or want to lend to verified Namibian borrowers, QuickShare gives you a secure and transparent platform.</p>
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
@endsection
