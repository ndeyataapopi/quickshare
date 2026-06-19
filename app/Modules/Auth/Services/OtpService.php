<?php

namespace App\Modules\Auth\Services;

use App\Exceptions\ApiException;
use App\Models\PhoneVerification;
use App\Models\User;

class OtpService
{
    protected int $otpLength = 6;
    protected int $expiryMinutes = 10;
    protected int $maxAttempts = 5;
    protected int $cooldownSeconds = 60;

    public function sendOtp(string $phone): PhoneVerification
    {
        $this->checkCooldown($phone);
        $this->invalidatePreviousOtps($phone);

        $otp = $this->generateOtp();

        $verification = PhoneVerification::create([
            'phone' => $phone,
            'otp' => $otp,
            'expires_at' => now()->addMinutes($this->expiryMinutes),
        ]);

        // TODO: Send OTP via SMS gateway (e.g., Twilio, Africa's Talking)
        // For development, OTP will be returned in the response

        return $verification;
    }

    public function verifyOtp(string $phone, string $otp): bool
    {
        $verification = PhoneVerification::where('phone', $phone)
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if (! $verification) {
            throw new ApiException('No pending verification found for this phone number.', 422);
        }

        if ($verification->isExpired()) {
            throw new ApiException('OTP has expired. Please request a new one.', 422);
        }

        if ($verification->hasExceededAttempts($this->maxAttempts)) {
            throw new ApiException('Maximum verification attempts exceeded. Please request a new OTP.', 422);
        }

        $verification->incrementAttempts();

        if ($verification->otp !== $otp) {
            $remaining = $this->maxAttempts - $verification->attempts;
            throw new ApiException("Invalid OTP. {$remaining} attempts remaining.", 422);
        }

        $verification->markVerified();

        return true;
    }

    public function markPhoneVerified(User $user): void
    {
        $user->update(['phone_verified_at' => now()]);
    }

    protected function generateOtp(): string
    {
        return str_pad((string) random_int(0, 999999), $this->otpLength, '0', STR_PAD_LEFT);
    }

    protected function checkCooldown(string $phone): void
    {
        $recent = PhoneVerification::where('phone', $phone)
            ->where('created_at', '>=', now()->subSeconds($this->cooldownSeconds))
            ->exists();

        if ($recent) {
            throw new ApiException(
                "Please wait {$this->cooldownSeconds} seconds before requesting a new OTP.",
                429
            );
        }
    }

    protected function invalidatePreviousOtps(string $phone): void
    {
        PhoneVerification::where('phone', $phone)
            ->whereNull('verified_at')
            ->update(['expires_at' => now()]);
    }
}
