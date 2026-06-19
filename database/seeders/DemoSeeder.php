<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\KYC\Models\KycSubmission;
use App\Modules\Loans\Models\Loan;
use App\Modules\Repayments\Models\Repayment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding 5000 users with related data...');

        // ── 1. Roles (must exist) ──────────────────────────────────────────
        $this->call(RoleSeeder::class);
        $clientRole = Role::where('name', 'client')->first();

        // ── 2. Users ───────────────────────────────────────────────────────
        $this->command->info('Creating 5000 client users...');

        $statusOptions = ['active', 'active', 'active', 'active', 'pending', 'suspended'];

        $users = User::factory()
            ->count(5000)
            ->sequence(fn ($seq) => [
                'status'      => $statusOptions[array_rand($statusOptions)],
                'trust_score' => fake()->randomFloat(2, 10, 100),
                'referred_by' => null,
            ])
            ->create();

        // Assign client role in bulk via pivot
        $this->command->info('Assigning roles...');
        $pivotRows = $users->map(fn ($u) => [
            'role_id'    => $clientRole->id,
            'model_type' => User::class,
            'model_id'   => $u->id,
        ])->toArray();

        foreach (array_chunk($pivotRows, 500) as $chunk) {
            DB::table('model_has_roles')->insertOrIgnore($chunk);
        }

        // ── 3. KYC Submissions (~90% of users) ────────────────────────────
        $this->command->info('Creating KYC submissions...');

        $kycStatuses  = ['approved', 'approved', 'approved', 'pending', 'pending', 'rejected'];
        $docTypes     = ['national_id', 'passport', 'drivers_license'];

        $kycRows = [];
        foreach ($users->random(min(1800, $users->count())) as $user) {
            $status = $kycStatuses[array_rand($kycStatuses)];
            $kycRows[] = [
                'user_id'          => $user->id,
                'status'           => $status,
                'submitted_at'     => now()->subDays(fake()->numberBetween(1, 180)),
                'reviewed_at'      => in_array($status, ['approved', 'rejected']) ? now()->subDays(fake()->numberBetween(0, 10)) : null,
                'rejection_reason' => $status === 'rejected' ? 'Documents unclear or expired' : null,
                'metadata'         => json_encode([
                    'document_type'   => $docTypes[array_rand($docTypes)],
                    'document_number' => strtoupper(Str::random(2)) . fake()->numerify('########'),
                    'issuing_country' => 'Namibia',
                    'expiry_date'     => now()->addYears(fake()->numberBetween(1, 8))->format('Y-m-d'),
                ]),
                'created_at' => now()->subDays(fake()->numberBetween(1, 180)),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($kycRows, 500) as $chunk) {
            DB::table('kyc_submissions')->insert($chunk);
        }

        // ── 4. Loans (~60% of users apply for loans) ──────────────────────
        $this->command->info('Creating loans...');

        $loanStatuses  = ['pending_review', 'marketplace', 'active', 'active', 'completed', 'completed', 'defaulted', 'cancelled'];
        $amounts       = [2000, 3000, 5000, 7500, 10000, 15000, 20000, 25000];
        $termDays      = [7, 14, 21, 30];

        $borrowers = $users->random(min(1200, $users->count()));
        $loanIds      = [];
        $loanRows     = [];
        $usedRefs     = DB::table('loans')->pluck('reference')->flip()->toArray();

        foreach ($borrowers as $borrower) {
            $loanCount = fake()->numberBetween(1, 3);
            for ($i = 0; $i < $loanCount; $i++) {
                do {
                    $ref = 'QS-' . strtoupper(Str::random(7));
                } while (isset($usedRefs[$ref]));
                $usedRefs[$ref] = true;

                $amount  = $amounts[array_rand($amounts)];
                $rate    = fake()->randomFloat(2, 10, 25);
                $days    = $termDays[array_rand($termDays)];
                $interest = round($amount * ($rate / 100) * ($days / 365), 2);
                $fee      = round($amount * 0.02, 2);
                $status   = $loanStatuses[array_rand($loanStatuses)];
                $createdAt = now()->subDays(fake()->numberBetween(1, 365));

                $loanRows[] = [
                    'borrower_id'      => $borrower->id,
                    'reference'        => $ref,
                    'requested_amount' => $amount,
                    'approved_amount'  => $amount,
                    'interest_rate'    => $rate,
                    'platform_fee'     => $fee,
                    'total_repayment'  => $amount + $interest + $fee,
                    'funded_amount'    => in_array($status, ['active', 'completed', 'defaulted']) ? $amount : 0,
                    'loan_term_days'   => $days,
                    'repayment_date'   => $createdAt->copy()->addDays($days)->toDateString(),
                    'status'           => $status,
                    'risk_score'       => fake()->randomFloat(2, 20, 95),
                    'submitted_at'     => $createdAt,
                    'approved_at'      => in_array($status, ['marketplace', 'active', 'completed', 'defaulted']) ? $createdAt->copy()->addDays(2) : null,
                    'disbursed_at'     => in_array($status, ['active', 'completed', 'defaulted']) ? $createdAt->copy()->addDays(5) : null,
                    'created_at'       => $createdAt,
                    'updated_at'       => now(),
                    'external_loan_id'    => null,
                    'external_provider'   => null,
                    'sync_status'         => null,
                    'last_synced_at'      => null,
                    'external_metadata'   => null,
                ];
            }
        }

        foreach (array_chunk($loanRows, 500) as $chunk) {
            DB::table('loans')->insert($chunk);
        }

        // ── 5. Funding Transactions (marketplace & active loans) ───────────
        $this->command->info('Creating funding transactions...');

        $fundableStatuses = ['marketplace', 'active', 'completed', 'defaulted'];
        $fundableLoans    = DB::table('loans')->whereIn('status', $fundableStatuses)->get();
        $lenders          = $users->random(min(800, $users->count()));
        $lenderIds        = $lenders->pluck('id')->toArray();

        $fundRows = [];
        foreach ($fundableLoans as $loan) {
            $numFunders = fake()->numberBetween(1, 4);
            for ($i = 0; $i < $numFunders; $i++) {
                $amount = fake()->randomElement([500, 1000, 2500, 5000]);
                $rate   = fake()->randomFloat(2, 10, 20);
                $expectedReturn = round($amount * (1 + ($rate / 100) * ($loan->loan_term_days / 365)), 2);
                $fStatus = in_array($loan->status, ['active', 'completed', 'defaulted']) ? 'confirmed' : 'pending';

                $fundRows[] = [
                    'loan_id'               => $loan->id,
                    'lender_id'             => $lenderIds[array_rand($lenderIds)],
                    'amount'                => $amount,
                    'interest_rate'         => $rate,
                    'expected_return'       => $expectedReturn,
                    'status'                => $fStatus,
                    'transaction_reference' => 'FUND-' . strtoupper(Str::random(12)),
                    'confirmed_at'          => $fStatus === 'confirmed' ? now()->subDays(fake()->numberBetween(1, 60)) : null,
                    'created_at'            => now()->subDays(fake()->numberBetween(1, 60)),
                    'updated_at'            => now(),
                ];
            }
        }

        foreach (array_chunk($fundRows, 500) as $chunk) {
            DB::table('funding_transactions')->insert($chunk);
        }

        // ── 6. Repayments (active, completed, defaulted loans) ─────────────
        $this->command->info('Creating repayments...');

        $repayableLoans = DB::table('loans')
            ->whereIn('status', ['active', 'completed', 'defaulted'])
            ->get();

        $repayRows = [];
        foreach ($repayableLoans as $loan) {
            $installments = fake()->numberBetween(1, 3);
            for ($i = 0; $i < $installments; $i++) {
                $principal = round($loan->approved_amount / $installments, 2);
                $interest  = round($principal * ($loan->interest_rate / 100) * ($loan->loan_term_days / 365), 2);
                $fee       = round($principal * 0.02, 2);
                $dueDate   = now()->subDays(fake()->numberBetween(0, 180));

                $isOverdue = $loan->status === 'defaulted' && $i === 0;
                $isPaid    = $loan->status === 'completed' || ($loan->status === 'active' && $i < $installments - 1);

                $repayRows[] = [
                    'loan_id'               => $loan->id,
                    'borrower_id'           => $loan->borrower_id,
                    'amount'                => $principal + $interest + $fee,
                    'principal'             => $principal,
                    'interest'              => $interest,
                    'penalty'               => $isOverdue ? round(($principal + $interest) * 0.05, 2) : 0,
                    'platform_fee'          => $fee,
                    'status'                => $isOverdue ? 'overdue' : ($isPaid ? 'paid' : 'pending'),
                    'due_date'              => $dueDate->toDateString(),
                    'paid_date'             => $isPaid ? $dueDate->addDays(fake()->numberBetween(0, 5))->toDateString() : null,
                    'days_overdue'          => $isOverdue ? fake()->numberBetween(5, 90) : 0,
                    'transaction_reference' => 'REP-' . strtoupper(Str::random(12)),
                    'payment_method'        => fake()->randomElement(['bank_transfer', 'mobile_money', 'card']),
                    'created_at'            => now()->subDays(fake()->numberBetween(1, 180)),
                    'updated_at'            => now(),
                ];
            }
        }

        foreach (array_chunk($repayRows, 500) as $chunk) {
            DB::table('repayments')->insert($chunk);
        }

        // ── 7. Referrals ───────────────────────────────────────────────────
        $this->command->info('Creating referral chains...');

        $referrerIds = $users->random(min(400, $users->count()))->pluck('id')->toArray();
        $usedPairs    = [];
        $referralRows = [];
        foreach ($users->random(min(600, $users->count())) as $user) {
            $referrerId = $referrerIds[array_rand($referrerIds)];
            $pairKey    = $referrerId . '_' . $user->id;
            if ($referrerId !== $user->id && !isset($usedPairs[$pairKey])) {
                $usedPairs[$pairKey] = true;
                $referralRows[] = [
                    'referrer_id'   => $referrerId,
                    'referred_id'   => $user->id,
                    'referral_code' => strtoupper(Str::random(8)),
                    'status'        => fake()->randomElement(['pending', 'completed', 'completed', 'completed', 'revoked']),
                    'created_at'    => now()->subDays(fake()->numberBetween(1, 200)),
                    'updated_at'    => now(),
                ];
            }
        }

        if (!empty($referralRows)) {
            foreach (array_chunk($referralRows, 500) as $chunk) {
                DB::table('referrals')->insertOrIgnore($chunk);
            }
        }

        $this->command->info('✓ Done! Summary:');
        $this->command->table(
            ['Entity', 'Count'],
            [
                ['Users',                DB::table('users')->count()],
                ['KYC Submissions',      DB::table('kyc_submissions')->count()],
                ['Loans',                DB::table('loans')->count()],
                ['Funding Transactions', DB::table('funding_transactions')->count()],
                ['Repayments',           DB::table('repayments')->count()],
                ['Referrals',            DB::table('referrals')->count()],
            ]
        );
    }
}
