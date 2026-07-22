@extends('layouts.public')

@section('title', 'Support')
@section('description', 'Get help with QuickShare — find answers to common questions, contact our support team, and access platform resources.')

@section('content')
<!-- PAGE HERO -->
<section class="page-hero">
    <div class="container">
        <h1>Support Center</h1>
        <p>
            Need help? Find answers to common questions or reach out to our team.
            We're here to support your QuickShare journey.
        </p>
    </div>
</section>

<!-- QUICK HELP -->
<section style="background:white;">
    <div class="container">
        <div class="section-header">
            <span>Quick Help</span>
            <h2>Common Topics</h2>
            <p>Find quick answers to the most common questions from borrowers and lenders.</p>
        </div>
        <div class="feature-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-user-plus" aria-hidden="true"></i></div>
                <h3>Getting Started</h3>
                <p>Register an account, complete KYC verification with your Namibian ID, and activate your profile to start borrowing or lending.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-file-circle-check" aria-hidden="true"></i></div>
                <h3>KYC Verification</h3>
                <p>Submit your identification documents for review. Our team verifies your identity to ensure platform security and trust.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></div>
                <h3>Trust Score</h3>
                <p>Your trust score is based on repayment history, KYC status, and account activity. Higher scores unlock better rates and limits.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i></div>
                <h3>Loan Funding</h3>
                <p>Borrowers: your loan is listed on the marketplace after admin review. Lenders: browse and fund loans that match your risk appetite.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-money-bill-transfer" aria-hidden="true"></i></div>
                <h3>Repayments</h3>
                <p>Repayments are tracked transparently. Borrowers repay on schedule to build their trust score. Lenders receive repayments with returns.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-lock" aria-hidden="true"></i></div>
                <h3>Security</h3>
                <p>We use SSL encryption, KYC verification, and fraud monitoring. Sensitive data is encrypted at rest and never shared without consent.</p>
            </div>
        </div>
    </div>
</section>

<!-- CONTACT OPTIONS -->
<section style="background:linear-gradient(to bottom,#f8fafc,#eef4ff);">
    <div class="container">
        <div class="section-header">
            <span>Still Need Help?</span>
            <h2>Contact Our Team</h2>
            <p>Can't find what you're looking for? Reach out and we'll get back to you.</p>
        </div>
        <div class="feature-grid" style="grid-template-columns:repeat(2,1fr);">
            <div class="feature-card" style="text-align:center;">
                <div class="feature-icon" style="margin:0 auto 25px;"><i class="fa-solid fa-envelope" aria-hidden="true"></i></div>
                <h3>Email Support</h3>
                <p>For general questions, account issues, or platform feedback.</p>
                <p style="margin-top:15px;"><a href="mailto:support@quickshare.nepticgroup.com" style="color:var(--primary);font-weight:600;">support@quickshare.nepticgroup.com</a></p>
            </div>
            <div class="feature-card" style="text-align:center;">
                <div class="feature-icon" style="margin:0 auto 25px;"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></div>
                <h3>Privacy & Compliance</h3>
                <p>For privacy, data, or compliance-related questions.</p>
                <p style="margin-top:15px;"><a href="mailto:privacy@quickshare.nepticgroup.com" style="color:var(--primary);font-weight:600;">privacy@quickshare.nepticgroup.com</a></p>
            </div>
        </div>
        <div style="text-align:center;margin-top:40px;">
            <a href="{{ route('faq') }}" class="btn btn-primary">
                <i class="fa-solid fa-circle-question" aria-hidden="true"></i>
                View Full FAQ
            </a>
            <a href="{{ route('contact') }}" class="btn btn-outline" style="margin-left:15px;border:1px solid var(--border);color:var(--dark);">
                <i class="fa-solid fa-phone" aria-hidden="true"></i>
                Contact Page
            </a>
        </div>
    </div>
</section>
@endsection
