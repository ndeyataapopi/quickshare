@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0"><i class="mdi mdi-cog mr-2"></i>Settings</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">Settings</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    <!-- Settings Navigation -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-0">
                    <ul class="nav nav-tabs nav-fill" id="settingsTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="general-tab" data-toggle="tab" href="#general" role="tab">
                                <i class="mdi mdi-cog mr-2"></i>General
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="notifications-tab" data-toggle="tab" href="#notifications" role="tab">
                                <i class="mdi mdi-bell mr-2"></i>Notifications
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="privacy-tab" data-toggle="tab" href="#privacy" role="tab">
                                <i class="mdi mdi-shield-account mr-2"></i>Privacy
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="security-tab" data-toggle="tab" href="#security" role="tab">
                                <i class="mdi mdi-lock mr-2"></i>Security
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="appearance-tab" data-toggle="tab" href="#appearance" role="tab">
                                <i class="mdi mdi-palette mr-2"></i>Appearance
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Settings Content -->
    <div class="tab-content" id="settingsTabContent">
        <!-- General Settings -->
        <div class="tab-pane fade show active" id="general" role="tabpanel">
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-uppercase mb-3">General Preferences</h6>
                            <form id="generalSettingsForm">
                                <div class="form-group">
                                    <label class="form-label">Language</label>
                                    <select class="form-control" name="language">
                                        <option value="en" selected>English</option>
                                        <option value="af">Afrikaans</option>
                                        <option value="de">German</option>
                                        <option value="fr">French</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Timezone</label>
                                    <select class="form-control" name="timezone">
                                        <option value="Africa/Windhoek" selected>Windhoek (GMT+2)</option>
                                        <option value="Africa/Johannesburg">Johannesburg (GMT+2)</option>
                                        <option value="Africa/Cairo">Cairo (GMT+2)</option>
                                        <option value="Europe/London">London (GMT+0)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Currency Display</label>
                                    <select class="form-control" name="currency">
                                        <option value="NAD" selected>Namibian Dollar (N$)</option>
                                        <option value="USD">US Dollar ($)</option>
                                        <option value="EUR">Euro (€)</option>
                                        <option value="ZAR">South African Rand (R)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Date Format</label>
                                    <select class="form-control" name="date_format">
                                        <option value="M j, Y" selected>Jun 15, 2024</option>
                                        <option value="j M Y">15 Jun 2024</option>
                                        <option value="Y-m-d">2024-06-15</option>
                                        <option value="d/m/Y">15/06/2024</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="mdi mdi-content-save mr-1"></i> Save General Settings
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-uppercase mb-3">Account Settings</h6>
                            <form id="accountSettingsForm">
                                <div class="form-group">
                                    <label class="form-label">Default Loan Amount</label>
                                    <input type="number" class="form-control" name="default_loan_amount" 
                                           value="5000" min="100" max="100000" step="100">
                                    <small class="form-text text-muted">Pre-filled amount when applying for loans</small>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Default Loan Term</label>
                                    <select class="form-control" name="default_loan_term">
                                        <option value="30">30 days</option>
                                        <option value="60" selected>60 days</option>
                                        <option value="90">90 days</option>
                                        <option value="180">180 days</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="auto_invest" id="autoInvest">
                                        <label class="form-check-label" for="autoInvest">
                                            Enable Auto-Invest
                                        </label>
                                    </div>
                                    <small class="form-text text-muted">Automatically invest available funds in eligible loans</small>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="quick_apply" id="quickApply" checked>
                                        <label class="form-check-label" for="quickApply">
                                            Enable Quick Apply
                                        </label>
                                    </div>
                                    <small class="form-text text-muted">Skip confirmation steps for familiar loan applications</small>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="mdi mdi-content-save mr-1"></i> Save Account Settings
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notifications Settings -->
        <div class="tab-pane fade" id="notifications" role="tabpanel">
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-uppercase mb-3">Email Notifications</h6>
                            <form id="emailNotificationsForm">
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="email_loan_status" id="emailLoanStatus" checked>
                                        <label class="form-check-label" for="emailLoanStatus">
                                            Loan Status Updates
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="email_payment_reminders" id="emailPaymentReminders" checked>
                                        <label class="form-check-label" for="emailPaymentReminders">
                                            Payment Reminders
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="email_funding_opportunities" id="emailFundingOpportunities" checked>
                                        <label class="form-check-label" for="emailFundingOpportunities">
                                            New Funding Opportunities
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="email_earnings" id="emailEarnings" checked>
                                        <label class="form-check-label" for="emailEarnings">
                                            Earnings Updates
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="email_referrals" id="emailReferrals" checked>
                                        <label class="form-check-label" for="emailReferrals">
                                            Referral Status Updates
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="email_marketing" id="emailMarketing">
                                        <label class="form-check-label" for="emailMarketing">
                                            Marketing and Promotions
                                        </label>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="mdi mdi-content-save mr-1"></i> Save Email Preferences
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-uppercase mb-3">SMS Notifications</h6>
                            <form id="smsNotificationsForm">
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="sms_loan_status" id="smsLoanStatus" checked>
                                        <label class="form-check-label" for="smsLoanStatus">
                                            Loan Status Updates
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="sms_payment_reminders" id="smsPaymentReminders" checked>
                                        <label class="form-check-label" for="smsPaymentReminders">
                                            Payment Reminders
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="sms_funding_opportunities" id="smsFundingOpportunities">
                                        <label class="form-check-label" for="smsFundingOpportunities">
                                            New Funding Opportunities
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="sms_security" id="smsSecurity" checked>
                                        <label class="form-check-label" for="smsSecurity">
                                            Security Alerts
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">SMS Quiet Hours</label>
                                    <select class="form-control" name="sms_quiet_hours">
                                        <option value="none">No quiet hours</option>
                                        <option value="22-08" selected>10 PM - 8 AM</option>
                                        <option value="20-08">8 PM - 8 AM</option>
                                        <option value="custom">Custom</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="mdi mdi-content-save mr-1"></i> Save SMS Preferences
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Privacy Settings -->
        <div class="tab-pane fade" id="privacy" role="tabpanel">
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-uppercase mb-3">Profile Privacy</h6>
                            <form id="privacySettingsForm">
                                <div class="form-group">
                                    <label class="form-label">Profile Visibility</label>
                                    <select class="form-control" name="profile_visibility">
                                        <option value="public">Public</option>
                                        <option value="members" selected>Members Only</option>
                                        <option value="private">Private</option>
                                    </select>
                                    <small class="form-text text-muted">Control who can see your profile information</small>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="show_real_name" id="showRealName" checked>
                                        <label class="form-check-label" for="showRealName">
                                            Show Real Name
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="show_email" id="showEmail">
                                        <label class="form-check-label" for="showEmail">
                                            Show Email Address
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="show_phone" id="showPhone">
                                        <label class="form-check-label" for="showPhone">
                                            Show Phone Number
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="show_investment_stats" id="showInvestmentStats" checked>
                                        <label class="form-check-label" for="showInvestmentStats">
                                            Show Investment Statistics
                                        </label>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="mdi mdi-content-save mr-1"></i> Save Privacy Settings
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-uppercase mb-3">Data & Analytics</h6>
                            <form id="dataSettingsForm">
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="analytics_tracking" id="analyticsTracking" checked>
                                        <label class="form-check-label" for="analyticsTracking">
                                            Allow Analytics Tracking
                                        </label>
                                    </div>
                                    <small class="form-text text-muted">Help us improve the service by tracking usage patterns</small>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="personalized_recommendations" id="personalizedRecommendations" checked>
                                        <label class="form-check-label" for="personalizedRecommendations">
                                            Personalized Recommendations
                                        </label>
                                    </div>
                                    <small class="form-text text-muted">Get personalized loan and investment recommendations</small>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="data_sharing" id="dataSharing">
                                        <label class="form-check-label" for="dataSharing">
                                            Share Anonymous Data
                                        </label>
                                    </div>
                                    <small class="form-text text-muted">Share anonymous usage data to improve the platform</small>
                                </div>
                                <div class="form-group">
                                    <button type="button" class="btn btn-outline-warning" id="downloadDataBtn">
                                        <i class="mdi mdi-download mr-1"></i> Download My Data
                                    </button>
                                </div>
                                <div class="form-group">
                                    <button type="button" class="btn btn-outline-danger" id="clearDataBtn">
                                        <i class="mdi mdi-delete mr-1"></i> Clear Activity Data
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Security Settings -->
        <div class="tab-pane fade" id="security" role="tabpanel">
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-uppercase mb-3">Authentication</h6>
                            <form id="securitySettingsForm">
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="two_factor_auth" id="twoFactorAuth">
                                        <label class="form-check-label" for="twoFactorAuth">
                                            Two-Factor Authentication
                                        </label>
                                    </div>
                                    <small class="form-text text-muted">Add an extra layer of security to your account</small>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="session_timeout" id="sessionTimeout" checked>
                                        <label class="form-check-label" for="sessionTimeout">
                                            Auto-logout on Inactivity
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Session Timeout (minutes)</label>
                                    <select class="form-control" name="session_timeout_duration">
                                        <option value="15">15 minutes</option>
                                        <option value="30" selected>30 minutes</option>
                                        <option value="60">1 hour</option>
                                        <option value="120">2 hours</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Login Alerts</label>
                                    <select class="form-control" name="login_alerts">
                                        <option value="all" selected>All logins</option>
                                        <option value="new_devices">New devices only</option>
                                        <option value="none">Never</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="mdi mdi-content-save mr-1"></i> Save Security Settings
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-uppercase mb-3">Active Sessions</h6>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                    <div>
                                        <strong>Current Session</strong>
                                        <br><small class="text-muted">Chrome on Windows • Windhoek</small>
                                        <br><small class="text-muted">Started 2 hours ago</small>
                                    </div>
                                    <span class="badge badge-success">Current</span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center p-3 border rounded">
                                    <div>
                                        <strong>Mobile App</strong>
                                        <br><small class="text-muted">iPhone Safari • Windhoek</small>
                                        <br><small class="text-muted">Started yesterday</small>
                                    </div>
                                    <button class="btn btn-sm btn-outline-danger">Revoke</button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <button class="btn btn-warning btn-sm" id="revokeAllSessionsBtn">
                                    <i class="mdi mdi-logout mr-1"></i> Revoke All Other Sessions
                                </button>
                            </div>
                            <hr>
                            <h6 class="card-title text-uppercase mb-3">Password Requirements</h6>
                            <div class="alert alert-info">
                                <small>
                                    <strong>Password must contain:</strong><br>
                                    • At least 8 characters<br>
                                    • One uppercase letter<br>
                                    • One lowercase letter<br>
                                    • One number<br>
                                    • One special character
                                </small>
                            </div>
                            <button class="btn btn-outline-primary btn-sm" id="changePasswordBtn">
                                <i class="mdi mdi-lock mr-1"></i> Change Password
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Appearance Settings -->
        <div class="tab-pane fade" id="appearance" role="tabpanel">
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-uppercase mb-3">Theme & Display</h6>
                            <form id="appearanceSettingsForm">
                                <div class="form-group">
                                    <label class="form-label">Theme</label>
                                    <select class="form-control" name="theme">
                                        <option value="light" selected>Light</option>
                                        <option value="dark">Dark</option>
                                        <option value="auto">Auto (System)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Font Size</label>
                                    <select class="form-control" name="font_size">
                                        <option value="small">Small</option>
                                        <option value="medium" selected>Medium</option>
                                        <option value="large">Large</option>
                                        <option value="extra-large">Extra Large</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Dashboard Layout</label>
                                    <select class="form-control" name="dashboard_layout">
                                        <option value="grid" selected>Grid View</option>
                                        <option value="list">List View</option>
                                        <option value="compact">Compact View</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="compact_mode" id="compactMode">
                                        <label class="form-check-label" for="compactMode">
                                            Compact Mode
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="show_animations" id="showAnimations" checked>
                                        <label class="form-check-label" for="showAnimations">
                                            Show Animations
                                        </label>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="mdi mdi-content-save mr-1"></i> Save Appearance Settings
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-uppercase mb-3">Dashboard Widgets</h6>
                            <form id="widgetsSettingsForm">
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="widget_balance" id="widgetBalance" checked>
                                        <label class="form-check-label" for="widgetBalance">
                                            Account Balance
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="widget_recent_loans" id="widgetRecentLoans" checked>
                                        <label class="form-check-label" for="widgetRecentLoans">
                                            Recent Loans
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="widget_quick_actions" id="widgetQuickActions" checked>
                                        <label class="form-check-label" for="widgetQuickActions">
                                            Quick Actions
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="widget_notifications" id="widgetNotifications" checked>
                                        <label class="form-check-label" for="widgetNotifications">
                                            Recent Notifications
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="widget_marketplace" id="widgetMarketplace">
                                        <label class="form-check-label" for="widgetMarketplace">
                                            Marketplace Highlights
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="widget_analytics" id="widgetAnalytics">
                                        <label class="form-check-label" for="widgetAnalytics">
                                            Analytics Summary
                                        </label>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="mdi mdi-content-save mr-1"></i> Save Widget Settings
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form submission handlers
    const forms = [
        'generalSettingsForm',
        'accountSettingsForm', 
        'emailNotificationsForm',
        'smsNotificationsForm',
        'privacySettingsForm',
        'dataSettingsForm',
        'securitySettingsForm',
        'appearanceSettingsForm',
        'widgetsSettingsForm'
    ];
    
    forms.forEach(formId => {
        const form = document.getElementById(formId);
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="mdi mdi-loading mdi-spin mr-1"></i> Saving...';
                
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                    showToast('Settings saved successfully!');
                }, 1500);
            });
        }
    });
    
    // Special button handlers
    document.getElementById('downloadDataBtn')?.addEventListener('click', function() {
        this.disabled = true;
        this.innerHTML = '<i class="mdi mdi-loading mdi-spin mr-1"></i> Processing...';
        
        setTimeout(() => {
            this.disabled = false;
            this.innerHTML = '<i class="mdi mdi-download mr-1"></i> Download My Data';
            showToast('Your data is being prepared. You will receive an email when it\'s ready.');
        }, 2000);
    });
    
    document.getElementById('clearDataBtn')?.addEventListener('click', function() {
        if (confirm('Are you sure you want to clear all activity data? This action cannot be undone.')) {
            this.disabled = true;
            this.innerHTML = '<i class="mdi mdi-loading mdi-spin mr-1"></i> Clearing...';
            
            setTimeout(() => {
                this.disabled = false;
                this.innerHTML = '<i class="mdi mdi-delete mr-1"></i> Clear Activity Data';
                showToast('Activity data cleared successfully.');
            }, 2000);
        }
    });
    
    document.getElementById('revokeAllSessionsBtn')?.addEventListener('click', function() {
        if (confirm('Are you sure you want to revoke all other sessions? You will be logged out from other devices.')) {
            this.disabled = true;
            this.innerHTML = '<i class="mdi mdi-loading mdi-spin mr-1"></i> Revoking...';
            
            setTimeout(() => {
                this.disabled = false;
                this.innerHTML = '<i class="mdi mdi-logout mr-1"></i> Revoke All Other Sessions';
                showToast('All other sessions have been revoked.');
            }, 1500);
        }
    });
    
    document.getElementById('changePasswordBtn')?.addEventListener('click', function() {
        showToast('Redirecting to password change page...');
    });
    
    // Tab change handler to save current tab state
    const tabButtons = document.querySelectorAll('#settingsTabs .nav-link');
    tabButtons.forEach(btn => {
        btn.addEventListener('shown.bs.tab', function() {
            // Save active tab to localStorage
            localStorage.setItem('activeSettingsTab', this.id);
        });
    });
    
    // Restore active tab on page load
    const activeTab = localStorage.getItem('activeSettingsTab');
    if (activeTab) {
        const tabButton = document.getElementById(activeTab);
        if (tabButton) {
            tabButton.click();
        }
    }
    
    // Toast notification helper
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        const alertClass = type === 'success' ? 'alert-success' : (type === 'warning' ? 'alert-warning' : 'alert-danger');
        const icon = type === 'success' ? 'check-circle' : (type === 'warning' ? 'alert' : 'close-circle');
        
        toast.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        toast.innerHTML = `<i class="mdi mdi-${icon} mr-2"></i>${message} <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>`;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 4000);
    }
});
</script>
@endsection
