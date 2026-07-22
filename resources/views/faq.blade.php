@extends('layouts.public')

@section('title', 'FAQ')
@section('description', 'Find answers to common questions about QuickShare — borrowing, lending, trust scores, security, and more on Namibia's peer-to-peer lending platform.')

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const faqItems = document.querySelectorAll('.faq-item');
        faqItems.forEach(item => {
            const question = item.querySelector('.faq-question');
            const toggleFaq = () => {
                const isActive = item.classList.toggle('active');
                question.setAttribute('aria-expanded', isActive ? 'true' : 'false');
            };
            question.addEventListener('click', toggleFaq);
            question.addEventListener('keydown', (e) => {
                if(e.key === 'Enter' || e.key === ' '){ e.preventDefault(); toggleFaq(); }
            });
        });
    });
</script>
@endpush

@section('content')
<!-- PAGE HERO -->
<section class="page-hero">
    <div class="container">
        <h1>Frequently Asked Questions</h1>
        <p>Find answers to common questions about QuickShare.</p>
    </div>
</section>

<!-- FAQ -->
<section style="background:white;">
    <div class="container">
        <div style="max-width:900px;margin:auto;">
            @php
            $faqs = [
                ['cat'=>'General','q'=>'What is QuickShare?','a'=>'QuickShare is a Namibian peer-to-peer lending platform that connects borrowers directly with lenders. Borrowers request loans, lenders fund them, and repayments are tracked transparently.'],
                ['cat'=>'General','q'=>'How is QuickShare different from banks?','a'=>'We connect borrowers and lenders directly, cutting out the traditional middleman. Borrowers get transparent rates based on their trust score, and lenders can earn returns by funding verified borrowers.'],
                ['cat'=>'Borrowers','q'=>'What are the requirements to borrow?','a'=>'You must be at least 18 years old, have a valid Namibian ID, complete KYC verification, and have an active QuickShare account. Your trust score must meet the minimum borrowing threshold.'],
                ['cat'=>'Borrowers','q'=>'What interest rates can I expect?','a'=>'Rates vary based on your trust score tier, loan amount, and repayment period. Bronze tier rates start at ' . (config('loan.trust_tiers.bronze.platform_fee_percent') + config('loan.trust_tiers.bronze.lender_return_percent')) . '% and Platinum offers the lowest at ' . (config('loan.trust_tiers.platinum.platform_fee_percent') + config('loan.trust_tiers.platinum.lender_return_percent')) . '%. Higher trust scores unlock lower rates.'],
                ['cat'=>'Borrowers','q'=>'How much can I borrow?','a'=>'Loan amounts range from N$' . number_format(config('loan.loan_limits.min_amount'), 0) . ' to N$' . number_format(config('loan.loan_limits.max_amount'), 0) . ' depending on your trust score tier. You can have one active loan at a time.'],
                ['cat'=>'Lenders','q'=>'What returns can I earn?','a'=>'Lender returns range from ' . config('loan.trust_tiers.platinum.lender_return_percent') . '% to ' . config('loan.trust_tiers.bronze.lender_return_percent') . '% depending on the borrower\'s trust score tier. Higher risk loans offer higher potential returns. Returns are not guaranteed.'],
                ['cat'=>'Lenders','q'=>'Is my investment protected?','a'=>'All lending carries risk. We mitigate this through KYC verification, trust scores, manual loan review, and a collections process. Returns are not guaranteed. We recommend diversifying across multiple loans.'],
                ['cat'=>'Security','q'=>'How secure is my data?','a'=>'We use 256-bit SSL encryption for all data transmission. Sensitive data is encrypted at rest. We never share your data without consent and restrict access to authorised personnel only.'],
                ['cat'=>'Security','q'=>'What happens if a borrower defaults?','a'=>'Our collections process works to recover funds. The borrower\'s trust score is negatively impacted, and overdue repayments are tracked. Lending carries risk and returns are not guaranteed.'],
                ['cat'=>'Trust Score','q'=>'How does the trust score work?','a'=>'Your trust score is based on repayment history, KYC verification, account age, and referral credibility. Higher scores unlock better rates and higher loan limits across Bronze, Silver, Gold, and Platinum tiers.'],
            ];
            @endphp

            <style>
                .faq-item{background:white;border-radius:18px;margin-bottom:18px;overflow:hidden;box-shadow:0 5px 15px rgba(15,23,42,.04);}
                .faq-question{padding:24px;display:flex;justify-content:space-between;align-items:center;cursor:pointer;font-weight:600;}
                .faq-answer{max-height:0;overflow:hidden;transition:max-height .3s ease;}
                .faq-answer p{padding:0 24px 24px;color:var(--muted);line-height:1.8;}
                .faq-item.active .faq-answer{max-height:300px;}
                .faq-cat{font-size:.75rem;color:var(--primary);font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;}
            </style>

            @foreach($faqs as $i => $faq)
            <div class="faq-item {{ $i === 0 ? 'active' : '' }}">
                <div class="faq-question" role="button" aria-expanded="{{ $i === 0 ? 'true' : 'false' }}" tabindex="0">
                    <div>
                        <div class="faq-cat">{{ $faq['cat'] }}</div>
                        {{ $faq['q'] }}
                    </div>
                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </div>
                <div class="faq-answer">
                    <p>{{ $faq['a'] }}</p>
                </div>
            </div>
            @endforeach

            <div style="text-align:center;margin-top:50px;">
                <h3>Still have questions?</h3>
                <p style="color:var(--muted);margin:15px 0 25px;">Our support team is here to help.</p>
                <a href="{{ route('contact') }}" class="btn btn-primary">
                    <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                    Contact Support
                </a>
                <a href="{{ route('support') }}" class="btn btn-outline" style="margin-left:15px;border:1px solid var(--border);color:var(--dark);">
                    <i class="fa-solid fa-headset" aria-hidden="true"></i>
                    Support Center
                </a>
            </div>
        </div>
    </div>
</section>
@endsection
