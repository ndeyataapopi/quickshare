<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FAQ - {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('dist/css/style.min.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/MaterialDesign-Webfont/5.3.45/css/materialdesignicons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>body{font-family:'Nunito',sans-serif;} .hero-section{background:linear-gradient(135deg,#4f9ef8 0%,#7c3aed 100%);padding:80px 0;}</style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background:rgba(0,0,0,.15);position:absolute;width:100%;z-index:10;">
        <div class="container">
            <a class="navbar-brand font-weight-bold" href="{{ url('/') }}"><span class="badge badge-light text-primary mr-1" style="font-size:14px">QS</span> QuickShare</a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#nav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="nav">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item"><a class="nav-link" href="{{ url('/') }}">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('about') }}">About</a></li>
                    <li class="nav-item"><a class="nav-link active" href="{{ route('faq') }}">FAQ</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Login</a></li>
                    <li class="nav-item"><a class="nav-link btn btn-light text-primary px-3 ml-2 rounded" href="{{ route('register') }}">Get Started</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <section class="hero-section text-white text-center"><div class="container pt-5"><h1 class="display-4 font-weight-bold mb-3">Frequently Asked Questions</h1><p class="lead mb-0">Find answers to common questions about QuickShare.</p></div></section>

    <section class="py-5 bg-white">
        <div class="container"><div class="row justify-content-center"><div class="col-lg-8">
            <h5 class="font-weight-bold mb-3">General Questions</h5>
            <div class="accordion" id="faqAccordion">
                @php
                $faqs = [
                    ['cat'=>'General','q'=>'What is QuickShare?','a'=>'QuickShare is a peer-to-peer lending platform that connects borrowers directly with lenders in Namibia, eliminating traditional banking barriers and offering competitive rates.'],
                    ['cat'=>'General','q'=>'How is QuickShare different from banks?','a'=>'We offer lower interest rates for borrowers and higher returns for lenders by cutting out the middleman. Our trust score system ensures responsible lending.'],
                    ['cat'=>'Borrowers','q'=>'What are the requirements to borrow?','a'=>'You must be at least 18 years old, have a valid Namibian ID, complete KYC verification, and have an active QuickShare account.'],
                    ['cat'=>'Borrowers','q'=>'What interest rates can I expect?','a'=>'Rates vary based on your trust score, loan amount, and repayment period. Higher trust scores unlock lower rates.'],
                    ['cat'=>'Lenders','q'=>'What returns can I earn?','a'=>'Returns range from 12% to 25% annually, depending on the risk profile of the loans you fund.'],
                    ['cat'=>'Lenders','q'=>'Is my investment protected?','a'=>'All investments carry risk. We mitigate this through trust scores, KYC verification, and collections processes. Returns are not guaranteed.'],
                    ['cat'=>'Security','q'=>'How secure is my data?','a'=>'We use bank-level 256-bit SSL encryption for all data transmission. Sensitive data is encrypted at rest. We never share your data without consent.'],
                    ['cat'=>'Security','q'=>'What happens if a borrower defaults?','a'=>'Our collections team works to recover funds. We recommend diversifying investments across multiple loans.'],
                ];
                @endphp
                @foreach($faqs as $i => $faq)
                <div class="card mb-2">
                    <div class="card-header p-0" id="h{{ $i }}">
                        <button class="btn btn-link btn-block text-left px-3 py-3 {{ $i > 0 ? 'collapsed' : '' }}" type="button" data-toggle="collapse" data-target="#c{{ $i }}">
                            <strong>{{ $faq['q'] }}</strong>
                            <span class="badge badge-light float-right">{{ $faq['cat'] }}</span>
                        </button>
                    </div>
                    <div id="c{{ $i }}" class="collapse {{ $i === 0 ? 'show' : '' }}" data-parent="#faqAccordion">
                        <div class="card-body text-muted">{{ $faq['a'] }}</div>
                    </div>
                </div>
                @endforeach
            </div>
            <div class="text-center mt-5">
                <h5>Still have questions?</h5>
                <a href="{{ route('contact') }}" class="btn btn-primary mt-2">Contact Support</a>
            </div>
        </div></div></div>
    </section>

    <footer class="bg-dark text-white py-4 text-center"><small>&copy; {{ date('Y') }} QuickShare. All rights reserved.</small></footer>
    <script src="{{ asset('dist/js/jquery.min.js') }}"></script>
    <script src="{{ asset('dist/js/bootstrap.bundle.min.js') }}"></script>
</body>
</html>
