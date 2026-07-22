@extends('layouts.public')

@section('title', 'Privacy Policy')
@section('description', 'QuickShare Privacy Policy — how we collect, use, and protect your personal data on our Namibian peer-to-peer lending platform.')

@section('content')
<!-- PAGE HERO -->
<section class="page-hero">
    <div class="container">
        <h1>Privacy Policy</h1>
        <p>Your privacy is important to us. This policy explains how we collect, use, and protect your data.</p>
    </div>
</section>

<!-- PRIVACY CONTENT -->
<section style="background:white;">
    <div class="container">
        <div style="max-width:900px;margin:auto;">
            <div class="content-card">
                <h3>1. Information We Collect</h3>
                <p>QuickShare collects personal information (name, email, phone, date of birth), identification documents (Namibian national ID), financial information, and transaction data including loan requests, funding transactions, and repayments.</p>
            </div>
            <div class="content-card">
                <h3>2. How We Use Your Information</h3>
                <p>We use your data to provide services, process transactions, verify identity through KYC, communicate with you, comply with legal obligations, and prevent fraud.</p>
            </div>
            <div class="content-card">
                <h3>3. Data Security</h3>
                <p>We implement 256-bit SSL encryption for all data transmission, encrypt sensitive data at rest, and restrict access to personal data to authorised personnel only.</p>
            </div>
            <div class="content-card">
                <h3>4. Data Sharing</h3>
                <p>We do not sell your personal information. We only share data with your consent, with lenders when required for a loan transaction, with service providers under strict confidentiality, or when required by law.</p>
            </div>
            <div class="content-card">
                <h3>5. Your Rights</h3>
                <p>You have the right to access, update, and delete your personal information, opt out of marketing communications, and request data portability.</p>
            </div>
            <div class="content-card">
                <h3>6. Cookies</h3>
                <p>We use cookies to improve your experience, analyse usage, and for security. You can control cookie settings through your browser.</p>
            </div>
            <div class="content-card">
                <h3>7. Data Retention</h3>
                <p>We retain your information only as long as necessary to provide services and comply with legal obligations.</p>
            </div>
            <div class="content-card">
                <h3>8. Contact Us</h3>
                <p>For privacy questions: <a href="mailto:privacy@quickshare.nepticgroup.com" style="color:var(--primary);font-weight:600;">privacy@quickshare.nepticgroup.com</a></p>
                <p><small>Last updated: {{ date('F j, Y') }}</small></p>
            </div>
        </div>
    </div>
</section>
@endsection
