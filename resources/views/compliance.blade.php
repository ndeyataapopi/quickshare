@extends('layouts.public')

@section('title', 'Compliance')
@section('description', 'QuickShare compliance information — KYC verification, data protection, and platform policies for a secure Namibian peer-to-peer lending environment.')

@section('content')
<!-- PAGE HERO -->
<section class="page-hero">
    <div class="container">
        <h1>Compliance</h1>
        <p>
            QuickShare is committed to maintaining a secure, transparent, and compliant
            peer-to-peer lending platform for Namibians.
        </p>
    </div>
</section>

<!-- COMPLIANCE DETAILS -->
<section style="background:white;">
    <div class="container">
        <div style="max-width:900px;margin:auto;">

            <div class="content-card">
                <h3><i class="fa-solid fa-id-card" style="color:var(--primary);" aria-hidden="true"></i> KYC Verification</h3>
                <p>All users must complete Know Your Customer (KYC) verification before borrowing or lending on QuickShare. This includes submitting a valid Namibian national ID and supporting documents for review by our compliance team.</p>
                <ul>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Namibian national ID required for all users</li>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Documents reviewed by platform administrators</li>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Rejected submissions can be resubmitted with corrections</li>
                </ul>
            </div>

            <div class="content-card">
                <h3><i class="fa-solid fa-lock" style="color:var(--primary);" aria-hidden="true"></i> Data Protection</h3>
                <p>We take data protection seriously. Personal information is encrypted at rest, and all data transmission uses SSL encryption. We never sell your personal data and only share it with your consent or when required by law.</p>
                <ul>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> 256-bit SSL encryption for all data transmission</li>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Sensitive data encrypted at rest</li>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Access to personal data restricted to authorised personnel</li>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Data retained only as long as necessary for services and legal compliance</li>
                </ul>
            </div>

            <div class="content-card">
                <h3><i class="fa-solid fa-scale-balanced" style="color:var(--primary);" aria-hidden="true"></i> Responsible Lending</h3>
                <p>QuickShare promotes responsible borrowing and lending. Our trust score system, loan limits, and affordability assessments help ensure users borrow within their means.</p>
                <ul>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Loan amounts capped based on trust score tier</li>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> One active loan per borrower at a time</li>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Affordability assessment before loan approval</li>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Manual review of all loan requests by administrators</li>
                </ul>
            </div>

            <div class="content-card">
                <h3><i class="fa-solid fa-magnifying-glass" style="color:var(--primary);" aria-hidden="true"></i> Fraud Prevention</h3>
                <p>We actively monitor the platform for fraudulent activity. Suspicious behaviour is flagged and investigated. Users found engaging in fraud, money laundering, or illegal activity will be suspended and reported to relevant authorities.</p>
                <ul>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Fraud monitoring and detection systems</li>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Suspicious activity flagged for review</li>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Prohibited activities include fraud, money laundering, and any illegal activity</li>
                </ul>
            </div>

            <div class="content-card">
                <h3><i class="fa-solid fa-file-contract" style="color:var(--primary);" aria-hidden="true"></i> Platform Policies</h3>
                <p>QuickShare operates under clear terms of service and privacy policies. All users agree to these terms upon registration. Loan agreements are generated for each loan and include the full repayment terms.</p>
                <ul>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Terms of Service agreed upon at registration</li>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Privacy Policy governs data handling</li>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Loan agreements generated with full repayment terms</li>
                    <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Platform fees disclosed before confirming any transaction</li>
                </ul>
            </div>

            <div class="content-card">
                <h3><i class="fa-solid fa-envelope" style="color:var(--primary);" aria-hidden="true"></i> Contact</h3>
                <p>For compliance, privacy, or legal questions, contact us at <a href="mailto:privacy@quickshare.nepticgroup.com" style="color:var(--primary);font-weight:600;">privacy@quickshare.nepticgroup.com</a></p>
                <p><small>Last updated: {{ date('F j, Y') }}</small></p>
            </div>

        </div>
    </div>
</section>
@endsection
