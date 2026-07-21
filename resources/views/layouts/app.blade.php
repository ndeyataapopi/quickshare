<!-- layout, navigation, header (name) in the view itself, then partials (section of a view) -->
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <!-- Tell the browser to be responsive to screen width -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <!-- Favicon icon -->
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('assets/images/favicon.png') }}">
    <title>{{ $title ?? config('app.name') }} - {{ $section ?? 'Dashboard' }}</title>
    <link rel="" href="#" />
    
    <!-- This Page CSS -->
    <link href="{{ asset('assets/libs/chartist/dist/chartist.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/libs/chartist-plugin-tooltips/dist/chartist-plugin-tooltip.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/extra-libs/jvector/jvector.css') }}" rel="stylesheet" />
    <link href="{{ asset('assets/extra-libs/jvector/jquery-jvectormap-2.0.2.css') }}" rel="stylesheet" />
    
    <!-- DataTables page plugin CSS -->
    <link href="{{ asset('assets/extra-libs/datatables.net-bs4/css/dataTables.bootstrap4.css') }}" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/extra-libs/datatables.net-bs4/css/responsive.dataTables.min.css') }}">

    <!-- Multiple Select page plugin CSS -->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/libs/select2/dist/css/select2.min.css') }}">

    <!-- Quill Editor Page CSS -->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/libs/quill/dist/quill.snow.css') }}">

    <!-- needed css -->
    <link href="{{ asset('dist/css/style.min.css') }}" rel="stylesheet">
    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
    <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
<![endif]-->

</head>

<body>
    
    <!-- Include loading overlay -->
    @include('layouts.loading-overlay')
    
    <!-- ============================================================== -->
    <!-- Main wrapper - style you can find in pages.scss -->
    <!-- ============================================================== -->
    <div id="main-wrapper">
        
        <!-- ============================================================== -->
        <!-- Topbar header - style you can find in pages.scss -->
        <!-- ============================================================== -->
        <header class="topbar">
            
            <nav class="navbar top-navbar navbar-expand-md navbar-dark">
                
                <div class="navbar-header border-right">
                    <!-- This is for the sidebar toggle which is visible on mobile only -->
                    <a class="nav-toggler waves-effect waves-light d-block d-md-none" href="javascript:void(0)"><i class="ti-menu ti-close"></i></a>
                    <a class="navbar-brand" href="index.html">
                        <!-- Logo icon -->
                        <b class="logo-icon">
                            <!--You can put here icon as well // <i class="wi wi-sunset"></i> //-->
                            <!-- Dark Logo icon -->
                            <img src="{{ asset('assets/images/logos/logo-icon.png') }}" alt="homepage" class="dark-logo" />
                            <!-- Light Logo icon -->
                            <img src="{{ asset('assets/images/logos/logo-light-icon.png') }}" alt="homepage" class="light-logo" />
                        </b>
                        <!--End Logo icon -->
                        <!-- Logo text -->
                        <span class="logo-text">
                             <!-- dark Logo text -->
                             <img src="{{ asset('assets/images/logos/logo-text.png') }}" alt="homepage" class="dark-logo" />
                             <!-- Light Logo text -->    
                             <img src="{{ asset('assets/images/logos/logo-light-text.png') }}" class="light-logo" alt="homepage" />
                        </span>
                    </a>
                    <!-- ============================================================== -->
                    <!-- End Logo -->
                    <!-- ============================================================== -->
                    <!-- ============================================================== -->
                    <!-- Toggle which is visible on mobile only -->
                    <!-- ============================================================== -->
                    <a class="topbartoggler d-block d-md-none waves-effect waves-light" href="javascript:void(0)" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation"><i class="ti-more"></i></a>
                </div>
                <!-- ============================================================== -->
                <!-- End Logo -->
                <!-- ============================================================== -->
                
                <div class="navbar-collapse collapse" id="navbarSupportedContent">
                    <!-- ============================================================== -->
                    <!-- toggle and nav items -->
                    <!-- ============================================================== -->
                    <ul class="navbar-nav float-left mr-auto">
                        <li class="nav-item d-none d-md-block"><a class="nav-link sidebartoggler waves-effect waves-light" href="javascript:void(0)" data-sidebartype="mini-sidebar"><i class="mdi mdi-menu font-18"></i></a></li>
                        
                        <!-- ============================================================== -->
                        <!-- Messages -->
                        <!-- ============================================================== -->
                        @auth @if(Auth::user()->hasRole('client'))
                        @php $topbarUnread = Auth::user()->unreadNotifications()->count(); @endphp
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('client.notifications.index') }}">
                                <i class="font-18 mdi mdi-bell"></i>
                                @if($topbarUnread > 0)<span class="badge badge-danger badge-pill" style="position:absolute;top:8px;font-size:10px;">{{ $topbarUnread }}</span>@endif
                            </a>
                        </li>
                        @endif @endauth
                        <li class="nav-item d-none dropdown">
                            <a class="nav-link dropdown-toggle waves-effect waves-dark" href="" id="2" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"> <i class="font-18 mdi mdi-gmail"></i>
                                <div class="notify">
                                    <span class="heartbit"></span>
                                    <span class="point"></span>
                                </div>
                            </a>
                            <div class="dropdown-menu dropdown-menu-left mailbox animated bounceInDown" aria-labelledby="2">
                                <ul class="list-style-none">
                                    <li>
                                        <div class="drop-title border-bottom">You have 4 new messanges</div>
                                    </li>
                                    <li>
                                        <div class="message-center message-body">
                                            <!-- Message -->
                                            <a href="javascript:void(0)" class="message-item">
                                                <span class="user-img"> <img src="{{ asset('assets/images/users/1.jpg') }}" alt="user" class="rounded-circle"> <span class="profile-status online pull-right"></span> </span>
                                                <span class="mail-contnet">
                                                    <h5 class="message-title">Pavan kumar</h5> <span class="mail-desc">Just see the my admin!</span> <span class="time">9:30 AM</span> </span>
                                            </a>
                                            <!-- Message -->
                                            <a href="javascript:void(0)" class="message-item">
                                                <span class="user-img"> <img src="{{ asset('assets/images/users/2.jpg') }}" alt="user" class="rounded-circle"> <span class="profile-status busy pull-right"></span> </span>
                                                <span class="mail-contnet">
                                                    <h5 class="message-title">Sonu Nigam</h5> <span class="mail-desc">I've sung a song! See you at</span> <span class="time">9:10 AM</span> </span>
                                            </a>
                                            <!-- Message -->
                                            <a href="javascript:void(0)" class="message-item">
                                                <span class="user-img"> <img src="{{ asset('assets/images/users/3.jpg') }}" alt="user" class="rounded-circle"> <span class="profile-status away pull-right"></span> </span>
                                                <span class="mail-contnet">
                                                    <h5 class="message-title">Arijit Sinh</h5> <span class="mail-desc">I am a singer!</span> <span class="time">9:08 AM</span> </span>
                                            </a>
                                            <!-- Message -->
                                            <a href="javascript:void(0)" class="message-item">
                                                <span class="user-img"> <img src="{{ asset('assets/images/users/4.jpg') }}" alt="user" class="rounded-circle"> <span class="profile-status offline pull-right"></span> </span>
                                                <span class="mail-contnet">
                                                    <h5 class="message-title">Pavan kumar</h5> <span class="mail-desc">Just see the my admin!</span> <span class="time">9:02 AM</span> </span>
                                            </a>
                                        </div>
                                    </li>
                                    <li>
                                        <a class="nav-link text-center link text-dark" href="javascript:void(0);"> <b>See all Notifications</b> <i class="fa fa-angle-right"></i> </a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        

                        
                    </ul>
                    
                    <!-- ============================================================== -->
                    <!-- Right side toggle and nav items -->
                    <!-- ============================================================== -->
                    <ul class="navbar-nav float-right">
                        
                        <!-- ============================================================== -->
                        <!-- Search -->
                        <!-- ============================================================== -->
                        <!-- <li class="nav-item search-box"> 
                            <form class="app-search d-none d-lg-block">
                                <input type="text" class="form-control" placeholder="Search...">
                                <a href="" class="active"><i class="fa fa-search"></i></a>
                            </form>
                        </li> -->
                        <!-- ============================================================== -->
                        <!-- User profile and search -->
                        <!-- ============================================================== -->
                        
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle waves-effect waves-dark" href="" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="rounded-circle d-inline-flex align-items-center justify-content-center bg-primary text-white" style="width:36px;height:36px;font-size:14px;font-weight:600;">
                                    {{ strtoupper(substr(Auth::user()->first_name ?? 'U', 0, 1)) }}{{ strtoupper(substr(Auth::user()->last_name ?? '', 0, 1)) }}
                                </span>
                                <span class="ml-2 font-medium d-none d-md-inline">{{ Auth::user()->first_name }} {{ Auth::user()->last_name }}</span><i class="fas fa-angle-down ml-1"></i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right user-dd animated flipInY">
                                <div class="d-flex no-block align-items-center p-3 mb-2 border-bottom">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary text-white" style="width:60px;height:60px;font-size:20px;font-weight:600;flex-shrink:0;">
                                        {{ strtoupper(substr(Auth::user()->first_name ?? 'U', 0, 1)) }}{{ strtoupper(substr(Auth::user()->last_name ?? '', 0, 1)) }}
                                    </div>
                                    <div class="ml-2">
                                        <h5 class="mb-0">{{ Auth::user()->first_name }} {{ Auth::user()->last_name }}</h5>
                                        <p class="mb-1 text-muted small">{{ Auth::user()->email }}</p>
                                        @if(Auth::user()->hasRole('admin'))
                                            <span class="badge badge-danger">Admin</span>
                                        @else
                                            <span class="badge badge-primary">{{ ucfirst(Auth::user()->roles->first()?->name ?? 'Client') }}</span>
                                        @endif
                                    </div>
                                </div>
                                @if(Auth::user()->hasRole('client'))
                                <a class="dropdown-item" href="{{ route('client.profile.edit') }}"><i class="ti-user mr-1 ml-1"></i> My Profile</a>
                                <a class="dropdown-item" href="{{ route('client.notifications.index') }}"><i class="mdi mdi-bell mr-1 ml-1"></i> Notifications</a>
                                @endif
                                <div class="dropdown-divider"></div>
                                <form id="logout-form-topbar" method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <a class="dropdown-item" href="#" onclick="event.preventDefault(); document.getElementById('logout-form-topbar').submit();">
                                        <i class="fa fa-power-off mr-1 ml-1"></i> Logout
                                    </a>
                                </form>
                            </div>
                        </li>
                        <!-- ============================================================== -->
                        <!-- User profile and search -->
                        <!-- ============================================================== -->
                    </ul>
                </div>
            </nav>
        </header>
        <!-- ============================================================== -->
        <!-- End Topbar header -->
        <!-- ============================================================== -->


        <!-- impersonation banner -->
        @if($impersonating ?? false)
            <div class="alert alert-warning rounded-0 mb-0 text-center border-0" style="z-index: 9999;">
                <strong>Impersonating:</strong> {{ $impersonatedUser?->first_name }} {{ $impersonatedUser?->last_name }}
                <form method="POST" action="{{ $impersonateAdminUrl }}" class="d-inline ml-3">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-dark">Return to Admin</button>
                </form>
            </div>
        @endif
        <!-- navigation layout blade -->
        @include('layouts.navigation')
        <!-- navigation layout blade -->



        <!-- ============================================================== -->
        <!-- Page wrapper  -->
        <!-- ============================================================== -->
        <div class="page-wrapper">
            <!-- Body -->
            @yield('content')
            <!-- end body -->

            <!-- ============================================================== -->
            <!-- footer -->
            <!-- ============================================================== -->
            <footer class="footer text-center">
                All Rights Reserved by Quickshare. Designed and Developed by
                <a href="https://nepticgroup.com">Neptic Group</a>.
            </footer>
            <!-- ============================================================== -->
            <!-- End footer -->
            <!-- ============================================================== -->
        </div>
        <!-- ============================================================== -->
        <!-- End Page wrapper  -->
        <!-- ============================================================== -->
    </div>
    <!-- ============================================================== -->
    <!-- End Wrapper -->
    <!-- ============================================================== -->

    <div class="chat-windows"></div>
    <!-- ============================================================== -->
    <!-- All Jquery -->
    <!-- ============================================================== -->
    <!-- <script data-cfasync="false" src="/cdn-cgi/scripts/5c5dd728/cloudflare-static/email-decode.min.js"></script> -->
    <script src="{{ asset('assets/libs/jquery/dist/jquery.min.js') }}"></script>
    <!-- Bootstrap tether Core JavaScript -->
    <script src="{{ asset('assets/libs/popper.js/dist/umd/popper.min.js') }}"></script>
    <script src="{{ asset('assets/libs/bootstrap/dist/js/bootstrap.min.js') }}"></script>
    <!-- apps -->
    <script src="{{ asset('dist/js/app.min.js') }}"></script>
    <script src="{{ asset('dist/js/app.init.minimal.js') }}"></script>
    <script src="{{ asset('dist/js/app-style-switcher.js') }}"></script>
    <!-- slimscrollbar scrollbar JavaScript -->
    <script src="{{ asset('assets/libs/perfect-scrollbar/dist/perfect-scrollbar.jquery.min.js') }}"></script>
    <script src="{{ asset('assets/extra-libs/sparkline/sparkline.js') }}"></script>
    <!--Wave Effects -->
    <script src="{{ asset('dist/js/waves.js') }}"></script>
    <!--Menu sidebar -->
    <script src="{{ asset('dist/js/sidebarmenu.js') }}"></script>
    
    <!--Custom JavaScript -->
    <script src="{{ asset('dist/js/custom.min.js') }}"></script>
    <!-- This Page JS -->
    <script src="{{ asset('assets/extra-libs/jvector/jquery-jvectormap-2.0.2.min.js') }}"></script>
    <script src="{{ asset('assets/extra-libs/jvector/jquery-jvectormap-world-mill-en.js') }}"></script>
    <script src="{{ asset('assets/extra-libs/jvector/jquery-jvectormap-in-mill.js') }}"></script>
    <script src="{{ asset('assets/extra-libs/jvector/jquery-jvectormap-us-aea-en.js') }}"></script>
    <script src="{{ asset('assets/extra-libs/jvector/jquery-jvectormap-uk-mill-en.js') }}"></script>
    <script src="{{ asset('assets/extra-libs/jvector/jquery-jvectormap-au-mill.js') }}"></script>
    <script src="{{ asset('assets/libs/chartist/dist/chartist.min.js') }}"></script>
    <script src="{{ asset('dist/js/pages/chartist/chartist-plugin-tooltip.js') }}"></script>
    <script src="{{ asset('dist/js/pages/dashboards/dashboard3.js') }}"></script>
    <script>
      $(function() {
        // Mark body as loaded to prevent FOUC
        document.documentElement.classList.remove('loading');
        document.body.classList.add('loaded');
        
        $('#usa').vectorMap({
          map : 'us_aea_en',
          backgroundColor : 'transparent',
          zoomOnScroll: false,
          regionStyle : {
              initial : {
                  fill : '#2cabe3'
              }
          }
        });
      });
    </script>

    <!--DataTables page plugins -->
    <script src="{{ asset('assets/extra-libs/datatables.net/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('assets/extra-libs/datatables.net-bs4/js/dataTables.responsive.min.js') }}"></script>
    <script src="{{ asset('dist/js/pages/datatable/datatable-basic.init.js') }}"></script>

    @stack('scripts')

</body>
</html>
