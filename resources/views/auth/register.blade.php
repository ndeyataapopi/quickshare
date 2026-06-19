@extends('layouts.auth', ['title' => 'Register', 'subtitle' => 'Create your account', 'showSidebar' => true])

@section('content')
<form method="POST" action="{{ route('register') }}">
    @csrf
    <input type="hidden" name="role" value="client">

    <!-- Personal Information -->
    <h6 class="fw-bold mb-3">Personal Information</h6>
    
    <div class="mb-3">
        <label for="referral_code" class="form-label">Referral Code</label>
        <input id="referral_code" type="text" name="referral_code" class="form-control" value="{{ old('referral_code') }}" placeholder="Enter referral code (optional)">
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="first_name" class="form-label">First Name</label>
            <input id="first_name" type="text" name="first_name" class="form-control" value="{{ old('first_name') }}" required autofocus autocomplete="given-name" placeholder="First name">
        </div>
        <div class="col-md-6 mb-3">
            <label for="last_name" class="form-label">Last Name</label>
            <input id="last_name" type="text" name="last_name" class="form-control" value="{{ old('last_name') }}" required autocomplete="family-name" placeholder="Last name">
        </div>
    </div>

    <div class="mb-3">
        <label for="national_id" class="form-label">National ID</label>
        <input id="national_id" type="text" name="national_id" class="form-control" value="{{ old('national_id') }}" required autocomplete="off" placeholder="Enter your national ID">
    </div>

    <div class="mb-3">
        <label for="email" class="form-label">Email Address</label>
        <input id="email" type="email" name="email" class="form-control" value="{{ old('email') }}" required autocomplete="email" placeholder="Enter your email">
    </div>

    <div class="mb-3">
        <label for="phone" class="form-label">Phone Number</label>
        <input id="phone" type="tel" name="phone" class="form-control" value="{{ old('phone') }}" required autocomplete="tel" placeholder="Enter your phone number">
    </div>

    <div class="mb-3">
        <label for="date_of_birth" class="form-label">Date of Birth</label>
        <input id="date_of_birth" type="date" name="date_of_birth" class="form-control" value="{{ old('date_of_birth') }}" required autocomplete="birthday">
    </div>

    <!-- Address Information -->
    <h6 class="fw-bold mb-3 mt-4">Address Information</h6>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="address_country" class="form-label">Country</label>
            <input id="address_country" type="text" name="address[country]" class="form-control" value="{{ old('address.country') }}" required placeholder="Country">
        </div>
        <div class="col-md-6 mb-3">
            <label for="address_city" class="form-label">City</label>
            <input id="address_city" type="text" name="address[city]" class="form-control" value="{{ old('address.city') }}" required placeholder="City">
        </div>
    </div>

    <div class="mb-3">
        <label for="address_suburb" class="form-label">Suburb (Optional)</label>
        <input id="address_suburb" type="text" name="address[suburb]" class="form-control" value="{{ old('address.suburb') }}" placeholder="Suburb">
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="address_street" class="form-label">Street Address</label>
            <input id="address_street" type="text" name="address[street]" class="form-control" value="{{ old('address.street') }}" required placeholder="Street address">
        </div>
        <div class="col-md-6 mb-3">
            <label for="address_house_number" class="form-label">House Number</label>
            <input id="address_house_number" type="text" name="address[house_number]" class="form-control" value="{{ old('address.house_number') }}" required placeholder="House number">
        </div>
    </div>

    <!-- Source of Income -->
    <h6 class="fw-bold mb-3 mt-4">Source of Income</h6>

    <div class="mb-3">
        <label for="profession" class="form-label">Employment Status</label>
        <select id="profession" name="source_of_income[profession]" required class="form-select" onchange="toggleEmploymentFields()">
            <option value="">Select Status</option>
            <option value="employed" {{ old('source_of_income.profession') == 'employed' ? 'selected' : '' }}>Employed</option>
            <option value="self-employed" {{ old('source_of_income.profession') == 'self-employed' ? 'selected' : '' }}>Self-Employed</option>
            <option value="unemployed" {{ old('source_of_income.profession') == 'unemployed' ? 'selected' : '' }}>Unemployed</option>
        </select>
    </div>

    <div class="row employment-field">
        <div class="col-md-6 mb-3">
            <label for="company_name" class="form-label">Company/Organization Name</label>
            <input id="company_name" type="text" name="source_of_income[company_name]" class="form-control" value="{{ old('source_of_income.company_name') }}" placeholder="Company name">
        </div>
        <div class="col-md-6 mb-3">
            <label for="work_city" class="form-label">Work City</label>
            <input id="work_city" type="text" name="source_of_income[city]" class="form-control" value="{{ old('source_of_income.city') }}" placeholder="Work city">
        </div>
    </div>

    <div class="mb-3 employment-field">
        <label for="work_country" class="form-label">Work Country</label>
        <input id="work_country" type="text" name="source_of_income[country]" class="form-control" value="{{ old('source_of_income.country') }}" placeholder="Work country">
    </div>

    <!-- Password -->
    <h6 class="fw-bold mb-3 mt-4">Security</h6>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="password" class="form-label">Password</label>
            <input id="password" type="password" name="password" class="form-control" required autocomplete="new-password" placeholder="Min 8 characters">
        </div>
        <div class="col-md-6 mb-3">
            <label for="password_confirmation" class="form-label">Confirm Password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required autocomplete="new-password" placeholder="Confirm password">
        </div>
    </div>

    <div class="mb-3">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="terms" required>
            <label class="form-check-label" for="terms">
                I agree to all <a href="{{ route('terms') }}" class="text-primary">Terms of Service</a> and <a href="{{ route('privacy') }}" class="text-primary">Privacy Policy</a>
            </label>
        </div>
    </div>
    
    <div class="d-grid">
        <button type="submit" class="btn btn-primary">
            Create Account
        </button>
    </div>
</form>
@endsection

@push('scripts')
<script>
function toggleEmploymentFields() {
    const profession = document.getElementById('profession').value;
    const employmentFields = document.querySelectorAll('.employment-field');
    
    if (profession === 'unemployed') {
        employmentFields.forEach(field => {
            field.style.display = 'none';
            const inputs = field.querySelectorAll('input');
            inputs.forEach(input => input.required = false);
        });
    } else {
        employmentFields.forEach(field => {
            field.style.display = 'block';
            const inputs = field.querySelectorAll('input');
            if (field.querySelector('#company_name')) {
                inputs.forEach(input => input.required = true);
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    toggleEmploymentFields();
});
</script>
@endpush