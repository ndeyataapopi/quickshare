<?php

namespace App\Modules\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Requests\RegisterRequest;
use App\Modules\Auth\Requests\SendOtpRequest;
use App\Modules\Auth\Requests\ValidateReferralRequest;
use App\Modules\Auth\Requests\VerifyOtpRequest;
use App\Modules\Auth\Services\OtpService;
use App\Modules\Auth\Services\ReferralService;
use App\Modules\Auth\Services\RegistrationService;
use App\Traits\ApiResponse;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected RegistrationService $registrationService,
        protected OtpService $otpService,
        protected ReferralService $referralService,
    ) {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->registrationService->register($request->validated());

        // Trigger Laravel's built-in email verification
        event(new Registered($user));

        // Generate Sanctum token
        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->created([
            'user' => $user,
            'token' => $token,
            'message' => 'Registration successful. Please verify your email and phone number.',
        ], 'Registration successful.');
    }

    public function validateReferralCode(ValidateReferralRequest $request): JsonResponse
    {
        $referralCode = $this->referralService->validateCode($request->referral_code);

        if (! $referralCode) {
            return $this->error('Invalid or inactive referral code.', 422);
        }

        return $this->success([
            'valid' => true,
            'referrer' => $referralCode->user->first_name,
        ], 'Referral code is valid.');
    }

    public function sendPhoneOtp(SendOtpRequest $request): JsonResponse
    {
        $verification = $this->otpService->sendOtp($request->phone);

        $responseData = ['message' => 'OTP sent successfully.'];

        // In development, include OTP in response
        if (app()->isLocal()) {
            $responseData['otp'] = $verification->otp;
        }

        return $this->success($responseData, 'OTP sent successfully.');
    }

    public function verifyPhoneOtp(VerifyOtpRequest $request): JsonResponse
    {
        $this->otpService->verifyOtp($request->phone, $request->otp);

        // If authenticated user, mark their phone as verified
        if ($user = $request->user()) {
            $this->otpService->markPhoneVerified($user);
            $this->checkAndActivateUser($user);

            return $this->success([
                'phone_verified' => true,
                'status' => $user->fresh()->status,
            ], 'Phone number verified successfully.');
        }

        return $this->success([
            'phone_verified' => true,
        ], 'Phone number verified successfully.');
    }

    protected function checkAndActivateUser($user): void
    {
        // Activate user once both email and phone are verified
        if ($user->fresh()->isFullyVerified() && $user->isPending()) {
            $user->activate();
            $this->referralService->completeReferral($user);
        }
    }
}
