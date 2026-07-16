<!-- ============================================================== -->
<!-- Left Sidebar - style you can find in sidebar.scss  -->
<!-- ============================================================== -->
<aside class="left-sidebar">
    <!-- Sidebar scroll-->
    <div class="scroll-sidebar">
        <!-- Sidebar navigation-->
        <nav class="sidebar-nav">
            <ul id="sidebarnav">
                @auth
                <li class="sidebar-item">
                    <a class="sidebar-link has-arrow waves-effect waves-dark profile-dd" href="javascript:void(0)" aria-expanded="false">
                        <img src="{{ asset('assets/images/users/1.jpg') }}" class="rounded-circle ml-2" width="30">
                        <span class="hide-menu">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</span>
                    </a>
                    <ul aria-expanded="false" class="collapse first-level">
                        <li class="sidebar-item">
                            <form id="logout-form" method="POST" action="{{ route('logout') }}" style="display: none;">
                                @csrf
                            </form>
                            <a class="sidebar-link" href="javascript:void(0)" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                <i class="fa fa-power-off mr-2 ml-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </li>

                @role('admin')
                <!-- Admin Menu -->
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark @if(request()->routeIs('admin.dashboard')) active @endif" href="{{ route('admin.dashboard') }}">
                        <i class="mdi mdi-view-dashboard"></i>
                        <span class="hide-menu">Dashboard</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark @if(request()->routeIs('admin.users.*')) active @endif" href="{{ route('admin.users.index') }}">
                        <i class="mdi mdi-account-multiple"></i>
                        <span class="hide-menu">Users</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark @if(request()->routeIs('admin.kyc.*')) active @endif" href="{{ route('admin.kyc.index') }}">
                        <i class="mdi mdi-account-card-details"></i>
                        <span class="hide-menu">KYC Review</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark @if(request()->routeIs('admin.loans.*')) active @endif" href="{{ route('admin.loans.index') }}">
                        <i class="mdi mdi-cash"></i>
                        <span class="hide-menu">Loans</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark @if(request()->routeIs('admin.funding.*')) active @endif" href="{{ route('admin.funding.index') }}">
                        <i class="mdi mdi-bank-transfer-in"></i>
                        <span class="hide-menu">Funding / Escrow</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark @if(request()->routeIs('admin.disbursements.*')) active @endif" href="{{ route('admin.disbursements.index') }}">
                        <i class="mdi mdi-send"></i>
                        <span class="hide-menu">Disbursements</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark @if(request()->routeIs('admin.repayments.*')) active @endif" href="{{ route('admin.repayments.index') }}">
                        <i class="mdi mdi-cash-usd"></i>
                        <span class="hide-menu">Repayments</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark @if(request()->routeIs('admin.collections.*')) active @endif" href="{{ route('admin.collections.index') }}">
                        <i class="mdi mdi-bank"></i>
                        <span class="hide-menu">Collections</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark @if(request()->routeIs('admin.fraud.*')) active @endif" href="{{ route('admin.fraud.index') }}">
                        <i class="mdi mdi-shield-alert"></i>
                        <span class="hide-menu">Fraud</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark @if(request()->routeIs('admin.audit.*')) active @endif" href="{{ route('admin.audit.index') }}">
                        <i class="mdi mdi-history"></i>
                        <span class="hide-menu">Audit Logs</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark @if(request()->routeIs('admin.reports.*')) active @endif" href="{{ route('admin.reports.index') }}">
                        <i class="mdi mdi-chart-line"></i>
                        <span class="hide-menu">Reports</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark @if(request()->routeIs('admin.settings.*')) active @endif" href="{{ route('admin.settings.index') }}">
                        <i class="mdi mdi-settings"></i>
                        <span class="hide-menu">Settings</span>
                    </a>
                </li>
                @endrole

                @role('client')
                <!-- Client Menu (Borrower & Lender) -->
                @php $kyc = auth()->user()->kycSubmission; $kycApproved = $kyc && $kyc->isApproved(); @endphp
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark @if(request()->routeIs('client.dashboard')) active @endif" href="{{ route('client.dashboard') }}">
                        <i class="mdi mdi-view-dashboard"></i>
                        <span class="hide-menu">Dashboard</span>
                    </a>
                </li>
                @if(!$kycApproved)
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark @if(request()->routeIs('client.kyc.*')) active @endif" href="{{ route('client.kyc.upload') }}">
                        <i class="mdi mdi-upload text-warning"></i>
                        <span class="hide-menu text-warning">KYC Verification</span>
                    </a>
                </li>
                @endif
                <li class="sidebar-item @if(!$kycApproved) disabled @endif">
                    <a class="sidebar-link waves-effect waves-dark @if(request()->routeIs('client.loans.*')) active @endif" @if($kycApproved) href="{{ route('client.loans.index') }}" @else href="javascript:void(0)" @endif>
                        <i class="mdi mdi-cash"></i>
                        <span class="hide-menu">My Loans</span>
                    </a>
                </li>
                <li class="sidebar-item @if(!$kycApproved) disabled @endif">
                    <a class="sidebar-link waves-effect waves-dark @if(request()->routeIs('client.repayments.*')) active @endif" @if($kycApproved) href="{{ route('client.repayments.index') }}" @else href="javascript:void(0)" @endif>
                        <i class="mdi mdi-cash-usd"></i>
                        <span class="hide-menu">Repayments</span>
                    </a>
                </li>
                <li class="sidebar-item @if(!$kycApproved) disabled @endif">
                    <a class="sidebar-link waves-effect waves-dark @if(request()->routeIs('client.marketplace.*')) active @endif" @if($kycApproved) href="{{ route('client.marketplace.index') }}" @else href="javascript:void(0)" @endif>
                        <i class="mdi mdi-tune"></i>
                        <span class="hide-menu">Marketplace</span>
                    </a>
                </li>
                <li class="sidebar-item @if(!$kycApproved) disabled @endif">
                    <a class="sidebar-link waves-effect waves-dark @if(request()->routeIs('client.investments.*') || request()->routeIs('client.funding.*')) active @endif" @if($kycApproved) href="{{ route('client.investments.index') }}" @else href="javascript:void(0)" @endif>
                        <i class="mdi mdi-trending-up"></i>
                        <span class="hide-menu">Investments</span>
                    </a>
                </li>
                <li class="sidebar-item @if(!$kycApproved) disabled @endif">
                    <a class="sidebar-link waves-effect waves-dark @if(request()->routeIs('client.earnings.*')) active @endif" @if($kycApproved) href="{{ route('client.earnings.index') }}" @else href="javascript:void(0)" @endif>
                        <i class="mdi mdi-cash-multiple"></i>
                        <span class="hide-menu">Earnings</span>
                    </a>
                </li>
                <li class="sidebar-item @if(!$kycApproved) disabled @endif">
                    <a class="sidebar-link waves-effect waves-dark @if(request()->routeIs('client.analytics')) active @endif" @if($kycApproved) href="{{ route('client.analytics') }}" @else href="javascript:void(0)" @endif>
                        <i class="mdi mdi-chart-bar"></i>
                        <span class="hide-menu">Analytics</span>
                    </a>
                </li>
                @if($kycApproved)
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark @if(request()->routeIs('client.kyc.*')) active @endif" href="{{ route('client.kyc.upload') }}">
                        <i class="mdi mdi-shield-check"></i>
                        <span class="hide-menu">KYC</span>
                    </a>
                </li>
                @endif
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark @if(request()->routeIs('client.trust-score.index')) active @endif" href="{{ route('client.trust-score.index') }}">
                        <i class="mdi mdi-account-star"></i>
                        <span class="hide-menu">Trust Score</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark @if(request()->routeIs('client.referrals.index')) active @endif" href="{{ route('client.referrals.index') }}">
                        <i class="mdi mdi-account-group"></i>
                        <span class="hide-menu">Referrals</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark @if(request()->routeIs('client.notifications.index')) active @endif" href="{{ route('client.notifications.index') }}">
                        <i class="mdi mdi-bell"></i>
                        <span class="hide-menu">Notifications</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link waves-effect waves-dark @if(request()->routeIs('client.profile.*')) active @endif" href="{{ route('client.profile.edit') }}">
                        <i class="mdi mdi-account-edit"></i>
                        <span class="hide-menu">Profile</span>
                    </a>
                </li>
                @endrole
                @endauth
            </ul>
        </nav>
        <!-- End Sidebar navigation -->
    </div>
    <!-- End Sidebar scroll-->
</aside>
<!-- ============================================================== -->
<!-- End Left Sidebar - style you can find in sidebar.scss  -->
<!-- ============================================================== -->
