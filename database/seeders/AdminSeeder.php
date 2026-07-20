<?php

namespace Database\Seeders;

use App\Models\ReferralCode;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    /**
     * Seed the default admin user.
     */
    public function run(): void
    {
        $referralCode = strtoupper(Str::random(8));

        $admin = User::firstOrCreate(
            ['email' => 'quickshare@nepticgroup.com'],
            [
                'first_name' => 'Admin',
                'last_name' => 'Quickshare',
                'national_id' => 'ADMIN000001',
                'phone' => '+26481000000',
                'date_of_birth' => '1990-01-01',
                'password' => Hash::make('password'),
                'referral_code' => $referralCode,
                'trust_score' => 100.00,
                'status' => 'active',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]
        );

        $admin->assignRole('admin');

        // Create the admin's referral code for bootstrapping
        ReferralCode::firstOrCreate(
            ['user_id' => $admin->id],
            [
                'code' => $admin->referral_code,
                'is_active' => true,
            ]
        );
    }
}
