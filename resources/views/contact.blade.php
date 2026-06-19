<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contact Us - {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('dist/css/style.min.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/MaterialDesign-Webfont/5.3.45/css/materialdesignicons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Nunito', sans-serif; }
        .hero-section { background: linear-gradient(135deg, #4f9ef8 0%, #7c3aed 100%); padding: 80px 0; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: rgba(0,0,0,.15); position:absolute; width:100%; z-index:10;">
        <div class="container">
            <a class="navbar-brand font-weight-bold" href="{{ url('/') }}"><span class="badge badge-light text-primary mr-1" style="font-size:14px">QS</span> QuickShare</a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#nav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="nav">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item"><a class="nav-link" href="{{ url('/') }}">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('about') }}">About</a></li>
                    <li class="nav-item"><a class="nav-link active" href="{{ route('contact') }}">Contact</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Login</a></li>
                    <li class="nav-item"><a class="nav-link btn btn-light text-primary px-3 ml-2 rounded" href="{{ route('register') }}">Get Started</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <section class="hero-section text-white text-center"><div class="container pt-5"><h1 class="display-4 font-weight-bold mb-3">Contact Us</h1><p class="lead mb-0">We'd love to hear from you. Get in touch with our team.</p></div></section>

    <section class="py-5 bg-white">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h4 class="font-weight-bold mb-4">Get in Touch</h4>
                    <div class="d-flex mb-3"><div class="mr-3"><i class="mdi mdi-email text-primary" style="font-size:24px"></i></div><div><strong>Email</strong><br><span class="text-muted">support@quickshare.com</span></div></div>
                    <div class="d-flex mb-3"><div class="mr-3"><i class="mdi mdi-phone text-success" style="font-size:24px"></i></div><div><strong>Phone</strong><br><span class="text-muted">+264 61 123 456</span></div></div>
                    <div class="d-flex mb-3"><div class="mr-3"><i class="mdi mdi-map-marker text-danger" style="font-size:24px"></i></div><div><strong>Address</strong><br><span class="text-muted">Windhoek, Namibia</span></div></div>
                    <div class="mt-4">
                        <strong>Follow Us</strong>
                        <div class="mt-2">
                            <a href="#" class="btn btn-outline-primary btn-sm mr-1"><i class="mdi mdi-twitter"></i></a>
                            <a href="#" class="btn btn-outline-primary btn-sm mr-1"><i class="mdi mdi-facebook"></i></a>
                            <a href="#" class="btn btn-outline-primary btn-sm"><i class="mdi mdi-instagram"></i></a>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-body p-4">
                            <h5 class="card-title mb-4">Send a Message</h5>
                            <form>
                                <div class="row">
                                    <div class="col-md-6"><div class="form-group"><label>Name</label><input type="text" class="form-control" placeholder="Your name"></div></div>
                                    <div class="col-md-6"><div class="form-group"><label>Email</label><input type="email" class="form-control" placeholder="you@example.com"></div></div>
                                </div>
                                <div class="form-group"><label>Subject</label><input type="text" class="form-control" placeholder="How can we help?"></div>
                                <div class="form-group"><label>Message</label><textarea class="form-control" rows="5" placeholder="Your message"></textarea></div>
                                <button type="submit" class="btn btn-primary btn-block">Send Message</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-dark text-white py-4 text-center"><small>&copy; {{ date('Y') }} QuickShare. All rights reserved.</small></footer>
    <script src="{{ asset('dist/js/jquery.min.js') }}"></script>
    <script src="{{ asset('dist/js/bootstrap.bundle.min.js') }}"></script>
</body>
</html>
