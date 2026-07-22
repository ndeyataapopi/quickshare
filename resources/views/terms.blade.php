@extends('layouts.public')

@section('title', 'Terms of Service')
@section('description', 'QuickShare Terms of Service — the terms and conditions for using our Namibian peer-to-peer lending platform.')

@section('content')
<!-- PAGE HERO -->
<section class="page-hero">
    <div class="container">
        <h1>Terms of Service</h1>
        <p>Please read these terms carefully before using QuickShare.</p>
    </div>
</section>

<!-- TERMS CONTENT -->
<section style="background:white;">
    <div class="container">
        <div style="max-width:900px;margin:auto;">
            <div class="content-card">
                <h3>1. Acceptance of Terms</h3>
                <p>By accessing or using QuickShare, you agree to be bound by these Terms of Service.</p>
            </div>
            <div class="content-card">
                <h3>2. Eligibility</h3>
                <p>You must be at least 18 years old and legally capable of entering a binding contract to use QuickShare. A valid Namibian national ID is required for KYC verification.</p>
            </div>
            <div class="content-card">
                <h3>3. Account Registration</h3>
                <p>You agree to provide accurate, complete, and current information during registration and are responsible for maintaining your account credentials.</p>
            </div>
            <div class="content-card">
                <h3>4. Borrowing Terms</h3>
                <ul>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Loan requests are subject to administrator review and lender funding</li>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Interest rates are based on your trust score tier, loan amount, and term</li>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> You agree to repay loans per the agreed schedule</li>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Failure to repay may result in collections action and trust score impact</li>
                </ul>
            </div>
            <div class="content-card">
                <h3>5. Lending Terms</h3>
                <ul>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Lending involves risk, including borrower default risk</li>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Returns are not guaranteed</li>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Diversify across multiple loans to mitigate risk</li>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> QuickShare is not responsible for lending losses</li>
                </ul>
            </div>
            <div class="content-card">
                <h3>6. Trust Score</h3>
                <p>QuickShare uses a trust score based on repayment history, KYC verification, account age, and referral credibility. Higher scores qualify for better rates and higher loan limits across Bronze, Silver, Gold, and Platinum tiers.</p>
            </div>
            <div class="content-card">
                <h3>7. Fees</h3>
                <p>QuickShare charges platform fees on successful loan transactions. Fees are displayed before confirming any transaction. The default platform fee is {{ config('loan.fees.default_platform_fee_percent') }}%.</p>
            </div>
            <div class="content-card">
                <h3>8. Prohibited Activities</h3>
                <p>You agree not to use QuickShare for fraud, money laundering, or any illegal activity. Users found engaging in prohibited activities will be suspended and reported to relevant authorities.</p>
            </div>
            <div class="content-card">
                <h3>9. Limitation of Liability</h3>
                <p>QuickShare shall not be liable for indirect, incidental, or consequential damages arising from use of our platform.</p>
            </div>
            <div class="content-card">
                <h3>10. Contact Us</h3>
                <p>For questions: <a href="mailto:support@quickshare.nepticgroup.com" style="color:var(--primary);font-weight:600;">support@quickshare.nepticgroup.com</a></p>
                <p><small>Last updated: {{ date('F j, Y') }}</small></p>
            </div>
        </div>
    </div>
</section>
@endsection
