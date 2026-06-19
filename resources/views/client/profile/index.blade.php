@extends('layouts.app')
@section('content')
<div class="page-breadcrumb border-bottom">
    <div class="row">
        <div class="col-lg-3 col-md-4 col-xs-12 align-self-center">
            <h5 class="font-medium text-uppercase mb-0"><i class="mdi mdi-account mr-2"></i>Profile</h5>
        </div>
        <div class="col-lg-9 col-md-8 col-xs-12 align-self-center">
            <nav aria-label="breadcrumb" class="mt-2 float-md-right float-left">
                <ol class="breadcrumb mb-0 justify-content-end p-0">
                    <li class="breadcrumb-item"><a href="{{ route('client.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">Profile</li>
                </ol>
            </nav>
        </div>
    </div>
</div>
<div class="page-content container-fluid">
    @php
        $user = auth()->user();
        $profileCompletion = 65; // Mock calculation
        $kycStatus = $user->kycSubmission ? $user->kycSubmission->status : 'pending';
        $kycStatusColor = $kycStatus === 'approved' ? 'success' : ($kycStatus === 'pending' ? 'warning' : 'danger');
    @endphp

    <!-- Profile Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-3 text-center">
                            <div class="position-relative d-inline-block">
                                <img src="https://ui-avatars.com/api/?name={{ urlencode($user->first_name . ' ' . $user->last_name) }}&size=128&background=0d6efd&color=fff" 
                                     class="rounded-circle" alt="Profile" style="width: 128px; height: 128px;">
                                <button class="btn btn-sm btn-primary position-absolute" style="bottom: 5px; right: 5px;">
                                    <i class="mdi mdi-camera"></i>
                                </button>
                            </div>
                            <h5 class="mt-3 mb-1">{{ $user->first_name }} {{ $user->last_name }}</h5>
                            <p class="text-muted">{{ $user->email }}</p>
                            <div class="mt-2">
                                <span class="badge badge-{{ $kycStatusColor }}">
                                    <i class="mdi mdi-{{ $kycStatus === 'approved' ? 'check-circle' : ($kycStatus === 'pending' ? 'clock' : 'close-circle') }} mr-1"></i>
                                    KYC {{ ucfirst($kycStatus) }}
                                </span>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="text-center mb-3">
                                        <h4 class="text-primary mb-0">{{ number_format($user->trust_score ?? 0, 1) }}</h4>
                                        <small class="text-muted">Trust Score</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center mb-3">
                                        <h4 class="text-success mb-0">{{ $user->loans()->count() }}</h4>
                                        <small class="text-muted">Total Loans</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-center mb-3">
                                        <h4 class="text-info mb-0">{{ $profileCompletion }}%</h4>
                                        <small class="text-muted">Profile Complete</small>
                                    </div>
                                </div>
                            </div>
                            <div class="progress mb-3" style="height: 8px;">
                                <div class="progress-bar bg-primary" style="width: {{ $profileCompletion }}%"></div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <button class="btn btn-outline-primary btn-sm" id="editProfileBtn">
                                    <i class="mdi mdi-pencil mr-1"></i> Edit Profile
                                </button>
                                <button class="btn btn-outline-success btn-sm" id="completeProfileBtn">
                                    <i class="mdi mdi-check-all mr-1"></i> Complete Profile
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Profile Information -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title text-uppercase mb-3">Personal Information</h6>
                    <form id="profileForm">
                        <div class="form-group">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="first_name" value="{{ $user->first_name }}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="last_name" value="{{ $user->last_name }}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-control" name="email" value="{{ $user->email }}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone" value="{{ $user->phone ?? '' }}" placeholder="+264 81 123 4567">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" name="date_of_birth" value="{{ $user->date_of_birth ?? '' }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Gender</label>
                            <select class="form-control" name="gender">
                                <option value="">Select Gender</option>
                                <option value="male" {{ ($user->gender ?? '') === 'male' ? 'selected' : '' }}>Male</option>
                                <option value="female" {{ ($user->gender ?? '') === 'female' ? 'selected' : '' }}>Female</option>
                                <option value="other" {{ ($user->gender ?? '') === 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="mdi mdi-content-save mr-1"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title text-uppercase mb-3">Address Information</h6>
                    <form id="addressForm">
                        <div class="form-group">
                            <label class="form-label">Street Address</label>
                            <input type="text" class="form-control" name="address" value="{{ $user->address ?? '' }}" placeholder="123 Main Street">
                        </div>
                        <div class="form-group">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" name="city" value="{{ $user->city ?? '' }}" placeholder="Windhoek">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Postal Code</label>
                            <input type="text" class="form-control" name="postal_code" value="{{ $user->postal_code ?? '' }}" placeholder="9000">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Country</label>
                            <select class="form-control" name="country">
                                <option value="">Select Country</option>
                                <option value="NA" {{ ($user->country ?? '') === 'NA' ? 'selected' : '' }}>Namibia</option>
                                <option value="ZA" {{ ($user->country ?? '') === 'ZA' ? 'selected' : '' }}>South Africa</option>
                                <option value="BW" {{ ($user->country ?? '') === 'BW' ? 'selected' : '' }}>Botswana</option>
                                <option value="ZW" {{ ($user->country ?? '') === 'ZW' ? 'selected' : '' }}>Zimbabwe</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="mdi mdi-content-save mr-1"></i> Save Address
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Security Settings -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title text-uppercase mb-3">Security Settings</h6>
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="currentPassword" placeholder="Enter current password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" class="form-control" id="newPassword" placeholder="Enter new password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirmPassword" placeholder="Confirm new password">
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="twoFactorEnabled">
                            <label class="form-check-label" for="twoFactorEnabled">
                                Enable Two-Factor Authentication
                            </label>
                        </div>
                    </div>
                    <button class="btn btn-primary" id="updatePasswordBtn">
                        <i class="mdi mdi-lock mr-1"></i> Update Password
                    </button>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title text-uppercase mb-3">Notification Preferences</h6>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="emailNotifications" checked>
                            <label class="form-check-label" for="emailNotifications">
                                Email Notifications
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="smsNotifications" checked>
                            <label class="form-check-label" for="smsNotifications">
                                SMS Notifications
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="loanReminders" checked>
                            <label class="form-check-label" for="loanReminders">
                                Loan Payment Reminders
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="marketingEmails">
                            <label class="form-check-label" for="marketingEmails">
                                Marketing Emails
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="referralUpdates" checked>
                            <label class="form-check-label" for="referralUpdates">
                                Referral Status Updates
                            </label>
                        </div>
                    </div>
                    <button class="btn btn-primary" id="updateNotificationsBtn">
                        <i class="mdi mdi-bell mr-1"></i> Update Preferences
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Account Actions -->
    <div class="row">
        <div class="col-12">
            <div class="card border-danger">
                <div class="card-body">
                    <h6 class="card-title text-uppercase mb-3 text-danger">Account Actions</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="alert alert-warning">
                                <h6><i class="mdi mdi-download mr-2"></i>Download Your Data</h6>
                                <p class="mb-2 small">Request a copy of all your personal data and account information.</p>
                                <button class="btn btn-warning btn-sm" id="downloadDataBtn">
                                    <i class="mdi mdi-download mr-1"></i> Request Data
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-danger">
                                <h6><i class="mdi mdi-account-remove mr-2"></i>Delete Account</h6>
                                <p class="mb-2 small">Permanently delete your account and all associated data. This action cannot be undone.</p>
                                <button class="btn btn-danger btn-sm" id="deleteAccountBtn">
                                    <i class="mdi mdi-delete mr-1"></i> Delete Account
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Profile form submission
    document.getElementById('profileForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="mdi mdi-loading mdi-spin mr-1"></i> Saving...';
        
        setTimeout(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="mdi mdi-content-save mr-1"></i> Save Changes';
            showToast('Profile updated successfully!');
        }, 1500);
    });
    
    // Address form submission
    document.getElementById('addressForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const submitBtn = this.querySelector('button[type="submit"]');
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="mdi mdi-loading mdi-spin mr-1"></i> Saving...';
        
        setTimeout(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="mdi mdi-content-save mr-1"></i> Save Address';
            showToast('Address updated successfully!');
        }, 1500);
    });
    
    // Update password
    document.getElementById('updatePasswordBtn').addEventListener('click', function() {
        const currentPassword = document.getElementById('currentPassword').value;
        const newPassword = document.getElementById('newPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        
        if (!currentPassword || !newPassword || !confirmPassword) {
            showToast('Please fill in all password fields', 'warning');
            return;
        }
        
        if (newPassword !== confirmPassword) {
            showToast('New passwords do not match', 'warning');
            return;
        }
        
        if (newPassword.length < 8) {
            showToast('Password must be at least 8 characters long', 'warning');
            return;
        }
        
        this.disabled = true;
        this.innerHTML = '<i class="mdi mdi-loading mdi-spin mr-1"></i> Updating...';
        
        setTimeout(() => {
            this.disabled = false;
            this.innerHTML = '<i class="mdi mdi-lock mr-1"></i> Update Password';
            
            // Clear password fields
            document.getElementById('currentPassword').value = '';
            document.getElementById('newPassword').value = '';
            document.getElementById('confirmPassword').value = '';
            
            showToast('Password updated successfully!');
        }, 2000);
    });
    
    // Update notification preferences
    document.getElementById('updateNotificationsBtn').addEventListener('click', function() {
        this.disabled = true;
        this.innerHTML = '<i class="mdi mdi-loading mdi-spin mr-1"></i> Updating...';
        
        setTimeout(() => {
            this.disabled = false;
            this.innerHTML = '<i class="mdi mdi-bell mr-1"></i> Update Preferences';
            showToast('Notification preferences updated!');
        }, 1500);
    });
    
    // Download data request
    document.getElementById('downloadDataBtn').addEventListener('click', function() {
        this.disabled = true;
        this.innerHTML = '<i class="mdi mdi-loading mdi-spin mr-1"></i> Processing...';
        
        setTimeout(() => {
            this.disabled = false;
            this.innerHTML = '<i class="mdi mdi-download mr-1"></i> Request Data';
            showToast('Your data request has been submitted. You will receive an email when it\'s ready.');
        }, 2000);
    });
    
    // Delete account (with confirmation)
    document.getElementById('deleteAccountBtn').addEventListener('click', function() {
        if (confirm('Are you sure you want to delete your account? This action cannot be undone.')) {
            if (confirm('This will permanently delete all your data. Are you absolutely sure?')) {
                this.disabled = true;
                this.innerHTML = '<i class="mdi mdi-loading mdi-spin mr-1"></i> Deleting...';
                
                setTimeout(() => {
                    showToast('Account deletion request submitted. You will receive confirmation via email.');
                    this.disabled = false;
                    this.innerHTML = '<i class="mdi mdi-delete mr-1"></i> Delete Account';
                }, 2000);
            }
        }
    });
    
    // Edit profile button
    document.getElementById('editProfileBtn').addEventListener('click', function() {
        document.getElementById('profileForm').scrollIntoView({ behavior: 'smooth' });
        document.querySelector('#profileForm input[name="first_name"]').focus();
    });
    
    // Complete profile button
    document.getElementById('completeProfileBtn').addEventListener('click', function() {
        const incompleteFields = [];
        
        if (!document.querySelector('#profileForm input[name="phone"]').value) {
            incompleteFields.push('phone number');
        }
        if (!document.querySelector('#profileForm input[name="date_of_birth"]').value) {
            incompleteFields.push('date of birth');
        }
        if (!document.querySelector('#profileForm select[name="gender"]').value) {
            incompleteFields.push('gender');
        }
        if (!document.querySelector('#addressForm input[name="address"]').value) {
            incompleteFields.push('address');
        }
        
        if (incompleteFields.length > 0) {
            showToast(`Please complete: ${incompleteFields.join(', ')}`, 'warning');
        } else {
            showToast('Your profile is already complete!', 'success');
        }
    });
    
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
    
    // Animate progress bar on load
    setTimeout(() => {
        const progressBar = document.querySelector('.progress-bar');
        if (progressBar) {
            progressBar.style.transition = 'width 1s ease-in-out';
        }
    }, 500);
});
</script>
@endsection
