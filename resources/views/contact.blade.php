@extends('layouts.public')

@section('title', 'Contact')
@section('description', 'Contact QuickShare — get in touch with our team for support, questions, or feedback about our Namibian peer-to-peer lending platform.')

@push('scripts')
@if(config('services.recaptcha.site_key'))
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
@endif
@endpush

@section('content')
<!-- PAGE HERO -->
<section class="page-hero">
    <div class="container">
        <h1>Contact Us</h1>
        <p>
            We'd love to hear from you. Get in touch with our team for support,
            questions, or feedback about QuickShare.
        </p>
    </div>
</section>

<!-- CONTACT -->
<section style="background:white;">
    <div class="container">
        <div class="feature-grid" style="grid-template-columns:1fr 2fr;align-items:start;">
            <div>
                <div class="content-card" style="margin-bottom:0;">
                    <h3><i class="fa-solid fa-envelope" style="color:var(--primary);" aria-hidden="true"></i> Email</h3>
                    <p>For general questions and support:</p>
                    <p><a href="mailto:support@quickshare.nepticgroup.com" style="color:var(--primary);font-weight:600;">support@quickshare.nepticgroup.com</a></p>
                </div>
                <div class="content-card" style="margin-top:20px;margin-bottom:0;">
                    <h3><i class="fa-solid fa-shield-halved" style="color:var(--primary);" aria-hidden="true"></i> Privacy & Compliance</h3>
                    <p>For privacy, data, or compliance questions:</p>
                    <p><a href="mailto:privacy@quickshare.nepticgroup.com" style="color:var(--primary);font-weight:600;">privacy@quickshare.nepticgroup.com</a></p>
                </div>
                <div class="content-card" style="margin-top:20px;margin-bottom:0;">
                    <h3><i class="fa-solid fa-location-dot" style="color:var(--primary);" aria-hidden="true"></i> Location</h3>
                    <p>Windhoek, Namibia</p>
                </div>
            </div>
            <div class="content-card" style="margin-bottom:0;">
                <h3>Send a Message</h3>
                <p>Fill out the form below and we'll get back to you via email.</p>

                @if(session('success'))
                    <div style="background:#ecfdf5;border:1px solid #10b981;color:#065f46;padding:16px 20px;border-radius:14px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        {{ session('success') }}
                    </div>
                @endif

                @if($errors->any())
                    <div style="background:#fef2f2;border:1px solid #ef4444;color:#991b1b;padding:16px 20px;border-radius:14px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
                        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                        {{ $errors->first() }}
                    </div>
                @endif

                <form action="{{ route('contact.submit') }}" method="POST" style="margin-top:25px;">
                    @csrf
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
                        <div>
                            <label for="name" style="display:block;margin-bottom:8px;font-weight:600;">Name</label>
                            <input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="Your name" required style="width:100%;padding:14px;border-radius:14px;border:1px solid var(--border);font-family:inherit;font-size:1rem;">
                        </div>
                        <div>
                            <label for="email" style="display:block;margin-bottom:8px;font-weight:600;">Email</label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" required style="width:100%;padding:14px;border-radius:14px;border:1px solid var(--border);font-family:inherit;font-size:1rem;">
                        </div>
                    </div>
                    <div style="margin-bottom:20px;">
                        <label for="subject" style="display:block;margin-bottom:8px;font-weight:600;">Subject</label>
                        <input type="text" id="subject" name="subject" value="{{ old('subject') }}" placeholder="How can we help?" required style="width:100%;padding:14px;border-radius:14px;border:1px solid var(--border);font-family:inherit;font-size:1rem;">
                    </div>
                    <div style="margin-bottom:25px;">
                        <label for="message" style="display:block;margin-bottom:8px;font-weight:600;">Message</label>
                        <textarea id="message" name="message" rows="5" placeholder="Your message" required style="width:100%;padding:14px;border-radius:14px;border:1px solid var(--border);font-family:inherit;font-size:1rem;resize:vertical;">{{ old('message') }}</textarea>
                    </div>

                    @if(config('services.recaptcha.site_key'))
                        <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}" style="margin-bottom:25px;"></div>
                    @endif

                    <button type="submit" class="btn btn-primary" style="width:100%;">
                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                        Send Message
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- SUPPORT LINK -->
<section style="background:linear-gradient(to bottom,#f8fafc,#eef4ff);">
    <div class="container" style="text-align:center;">
        <div class="section-header">
            <span>Need More Help?</span>
            <h2>Visit Our Support Center</h2>
            <p>Find answers to common questions in our FAQ and support pages.</p>
        </div>
        <div class="hero-buttons" style="justify-content:center;">
            <a href="{{ route('faq') }}" class="btn btn-primary">
                <i class="fa-solid fa-circle-question" aria-hidden="true"></i>
                View FAQ
            </a>
            <a href="{{ route('support') }}" class="btn btn-outline" style="border:1px solid var(--border);color:var(--dark);">
                <i class="fa-solid fa-headset" aria-hidden="true"></i>
                Support Center
            </a>
        </div>
    </div>
</section>
@endsection
