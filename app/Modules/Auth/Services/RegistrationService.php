<?php

namespace App\Modules\Auth\Services;

use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\Address;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\SourceOfIncome;
use App\Models\User;
use App\Modules\Auth\Events\UserRegistered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegistrationService
{
    public function register(array $data): User
    {
        $referralCode = $this->validateReferralCode($data['referral_code']);

        return DB::transaction(function () use ($data, $referralCode) {
            $user = $this->createUser($data, $referralCode);
            $this->createAddress($user, $data['address']);
            $this->createSourceOfIncome($user, $data['source_of_income']);
            $this->generateReferralCode($user);
            $this->trackReferral($referralCode, $user);

            // Assign role - client gets both borrower and lender roles
            // $user->assignRole('borrower');
            // $user->assignRole('lender');
            $user->assignRole(UserRole::CLIENT->value);

            event(new UserRegistered($user));

            return $user->load(['address', 'sourceOfIncome', 'referralCode', 'referrer']);
        });
    }

    protected function validateReferralCode(string $code): ReferralCode
    {
        $referralCode = ReferralCode::where('code', $code)->first();

        if (! $referralCode) {
            throw new ApiException('Invalid referral code.', 422);
        }

        if (! $referralCode->isUsable()) {
            throw new ApiException('This referral code is no longer active.', 422);
        }

        return $referralCode;
    }

    protected function createUser(array $data, ReferralCode $referralCode): User
    {
        return User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'national_id' => $data['national_id'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'date_of_birth' => $data['date_of_birth'],
            'password' => $data['password'],
            'referral_code' => $this->generateUniqueCode(),
            'referred_by' => $referralCode->user_id,
            'trust_score' => 50.00,
            'status' => 'pending',
        ]);
    }

    protected function createAddress(User $user, array $addressData): Address
    {
        return $user->address()->create([
            'country' => $addressData['country'],
            'city' => $addressData['city'],
            'suburb' => $addressData['suburb'] ?? null,
            'street' => $addressData['street'],
            'house_number' => $addressData['house_number'],
        ]);
    }

    protected function createSourceOfIncome(User $user, array $incomeData): SourceOfIncome
    {
        return $user->sourceOfIncome()->create([
            'profession' => $incomeData['profession'],
            'company_name' => $incomeData['company_name'] ?? null,
            'city' => $incomeData['city'] ?? null,
            'country' => $incomeData['country'] ?? null,
        ]);
    }

    protected function generateReferralCode(User $user): ReferralCode
    {
        return $user->referralCode()->create([
            'code' => $user->referral_code,
            'is_active' => true,
        ]);
    }

    protected function trackReferral(ReferralCode $referralCode, User $newUser): void
    {
        Referral::create([
            'referrer_id' => $referralCode->user_id,
            'referred_id' => $newUser->id,
            'referral_code' => $referralCode->code,
            'status' => 'pending',
        ]);

        $referralCode->incrementUsage();
    }

    protected function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }
}
