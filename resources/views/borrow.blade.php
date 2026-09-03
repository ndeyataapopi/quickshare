@extends('layouts.public')

@section('title', 'Borrow')
@section('description', 'Borrow money quickly on QuickShare — a Namibian peer-to-peer lending platform. Complete KYC verification, request a loan, and get funded by verified lenders.')

@section('content')
<!-- PAGE HERO -->
<section class="page-hero">
    <div class="container">
        <h1>Borrow Money on QuickShare</h1>
        <p>
            Request a loan from N${{ number_format(config('loan.loan_limits.min_amount'), 0) }}
            to N${{ number_format(config('loan.loan_limits.max_amount'), 0) }} with transparent rates
            based on your trust score. Get funded by verified Namibian lenders.
        </p>
    </div>
</section>

<!-- HOW BORROWING WORKS -->
<section style="background:white;">
    <div class="container">
        <div class="section-header">
            <span>How Borrowing Works</span>
            <h2>Four Simple Steps</h2>
            <p>From registration to funding — a transparent process designed for Namibian borrowers.</p>
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
                <p>Platform administrators review your request. Approved loans are listed on the marketplace for lenders to fund.</p>
            </div>
            <div class="step">
                <div class="step-number">4</div>
                <h4>Receive Funds</h4>
                <p>Once fully funded, the loan is disbursed to you. Repay on schedule to build your trust score.</p>
            </div>
        </div>
    </div>
</section>

<!-- LOAN DETAILS -->
<section style="background:linear-gradient(to bottom,#f8fafc,#eef4ff);">
    <div class="container">
        <div class="section-header">
            <span>Loan Details</span>
            <h2>What You Need to Know</h2>
        </div>
        <div class="feature-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-coins" aria-hidden="true"></i></div>
                <h3>Loan Amounts</h3>
                <p>Borrow from N${{ number_format(config('loan.loan_limits.min_amount'), 0) }} to N${{ number_format(config('loan.loan_limits.max_amount'), 0) }} depending on your trust score tier. Higher tiers unlock higher limits.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></div>
                <h3>Repayment Period</h3>
                <p>Choose a repayment period from {{ config('loan.loan_limits.min_term_days') }} to {{ config('loan.loan_limits.max_term_days') }} days. Your total repayment is calculated upfront — no hidden fees.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-percent" aria-hidden="true"></i></div>
                <h3>Interest Rates</h3>
                <p>Rates are based on your trust score tier. Bronze starts at {{ config('loan.trust_tiers.bronze.platform_fee_percent') + config('loan.trust_tiers.bronze.lender_return_percent') }}% and Platinum offers the lowest at {{ config('loan.trust_tiers.platinum.platform_fee_percent') + config('loan.trust_tiers.platinum.lender_return_percent') }}%.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></div>
                <h3>KYC Required</h3>
                <p>All borrowers must complete KYC verification with a valid Namibian ID. This protects the platform and builds trust with lenders.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></div>
                <h3>Build Your Score</h3>
                <p>Repay on time to increase your trust score. Higher scores unlock better rates, higher limits, and faster funding.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-circle-info" aria-hidden="true"></i></div>
                <h3>One Active Loan</h3>
                <p>You can have one active loan at a time. This helps you manage repayments responsibly and build a strong repayment history.</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta">
    <div class="container">
        <h2>Ready to Borrow?</h2>
        <p>Create your QuickShare account, complete KYC verification, and request your first loan today.</p>
        <div class="hero-buttons" style="justify-content:center;">
            <a href="{{ route('register') }}" class="btn btn-primary">
                Become a Borrower
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
