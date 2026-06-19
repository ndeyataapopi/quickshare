<?php
namespace Database\Seeders;
use App\Models\ReferralCode;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class Phase2Seeder extends Seeder
{
    const AMOUNTS  = [500,600,700,800,900,1000,1100,1200,1300,1400,1500];
    const TERMS    = [7,10,14,21,30];
    const RATE     = 30.0;
    const FEE_PCT  = 0.05;
    const METHODS  = ['bank_transfer','mobile_money','eft'];

    public function run(): void
    {
        $this->command->info('Phase 2: clearing tables...');
        $this->truncateAll();
        $this->call(RoleSeeder::class);

        $admin     = $this->mkUser('Admin',   'Quickshare', 'admin@quickshare.test',     '+26481000001', 100.0, 'active', 'admin');
        $borrower1 = $this->mkUser('Brave',   'Ndikwetepo', 'borrower1@quickshare.test', '+26481000002',  72.5, 'active', 'client');
        $lender1   = $this->mkUser('Lindiwe', 'Shivute',    'lender1@quickshare.test',   '+26481000003',  85.0, 'active', 'client');
        $client1   = $this->mkUser('Carlos',  'Amutenya',   'client1@quickshare.test',   '+26481000004',  55.0, 'active', 'client');

        $opts = ['active','active','active','active','pending','suspended'];
        $randoms = collect();
        for ($i = 0; $i < 46; $i++) {
            $randoms->push($this->mkUser(fake()->firstName(), fake()->lastName(),
                fake()->unique()->safeEmail(), '+264'.fake()->numerify('8########'),
                (float)fake()->randomFloat(1,15,95), $opts[array_rand($opts)], 'client'));
        }
        $allClients = collect([$borrower1,$lender1,$client1])->merge($randoms);

        foreach ([$admin,$borrower1,$lender1,$client1] as $u) {
            ReferralCode::firstOrCreate(['user_id'=>$u->id],['code'=>$u->referral_code,'is_active'=>true]);
        }

        $this->seedKyc($allClients, $borrower1, $lender1, $client1);
        $this->seedLoans($allClients, $borrower1, $lender1, $client1, $lender1);
        $this->seedReferrals($allClients);
        $this->seedTrustHistory($allClients);
        $this->seedAuditLogs($admin, $allClients);

        $this->command->table(['Entity','Count'],[
            ['Users',DB::table('users')->count()],
            ['KYC',DB::table('kyc_submissions')->count()],
            ['Loans',DB::table('loans')->count()],
            ['Funding',DB::table('funding_transactions')->count()],
            ['Repayments',DB::table('repayments')->count()],
            ['Investments',DB::table('investments')->count()],
        ]);
        $this->command->info('');
        $this->command->info('TEST CREDENTIALS (password: password)');
        $this->command->info('  admin@quickshare.test      — Admin');
        $this->command->info('  borrower1@quickshare.test  — Client (borrower, gold tier)');
        $this->command->info('  lender1@quickshare.test    — Client (lender, platinum tier)');
        $this->command->info('  client1@quickshare.test    — Client (silver tier)');
    }

    private function mkUser(string $fn, string $ln, string $email, string $phone, float $score, string $status, string $role): User
    {
        $u = User::create([
            'first_name'=>$fn,'last_name'=>$ln,'email'=>$email,
            'national_id'=>strtoupper(Str::random(2)).fake()->numerify('#######'),
            'phone'=>$phone,
            'date_of_birth'=>fake()->dateTimeBetween('-50 years','-20 years')->format('Y-m-d'),
            'password'=>Hash::make('password'),
            'referral_code'=>strtoupper(Str::random(8)),
            'trust_score'=>$score,'status'=>$status,'email_verified_at'=>now(),
        ]);
        $u->assignRole($role);
        return $u;
    }

    private function seedKyc($clients, $b1, $l1, $c1): void
    {
        $this->command->info('Seeding KYC...');
        $docTypes = ['national_id','passport','drivers_license'];
        $rows = [];
        foreach ($clients as $idx => $u) {
            if ($u->id === $b1->id || $u->id === $l1->id) { $st='approved'; }
            elseif ($u->id === $c1->id) { $st='pending'; }
            else { $opts=['approved','approved','approved','pending','pending','rejected']; $st=$opts[$idx%6]; }
            $rows[] = [
                'user_id'=>$u->id,'status'=>$st,
                'submitted_at'=>now()->subDays(rand(5,120)),
                'reviewed_at'=>in_array($st,['approved','rejected'])?now()->subDays(rand(0,5)):null,
                'rejection_reason'=>$st==='rejected'?'Documents unclear or expired':null,
                'metadata'=>json_encode(['document_type'=>$docTypes[array_rand($docTypes)],'document_number'=>strtoupper(Str::random(2)).fake()->numerify('#######'),'issuing_country'=>'Namibia','expiry_date'=>now()->addYears(rand(1,7))->format('Y-m-d')]),
                'created_at'=>now()->subDays(rand(5,120)),'updated_at'=>now(),
            ];
        }
        foreach (array_chunk($rows,100) as $chunk) DB::table('kyc_submissions')->insert($chunk);
    }

    private function seedLoans($clients, $b1, $l1, $c1, $mainLender): void
    {
        $this->command->info('Seeding loans + funding + repayments + investments...');
        $currency = config('loans.currency_symbol','N$');
        $lenderIds = $clients->where('id','!=',$b1->id)->pluck('id')->toArray();

        // Named test-user loans (deterministic lifecycle)
        $testLoans = [
            [$b1, 1000, 14, 'active'],
            [$b1, 800,  21, 'completed'],
            [$b1, 1200, 30, 'pending_review'],
            [$l1, 1500, 30, 'marketplace'],
            [$c1, 700,  14, 'pending_review'],
            [$c1, 900,  30, 'defaulted'],
        ];
        foreach ($testLoans as [$user, $amt, $days, $st]) {
            $this->insertLoan($user->id, $amt, $days, $st, $lenderIds, true);
        }

        // Random loans for 40 clients
        $borrowers = $clients->random(40);
        foreach ($borrowers as $u) {
            $n = rand(1,3);
            $statuses = ['pending_review','marketplace','partially_funded','active','active','completed','defaulted','cancelled'];
            for ($i=0; $i<$n; $i++) {
                $this->insertLoan($u->id, self::AMOUNTS[array_rand(self::AMOUNTS)], self::TERMS[array_rand(self::TERMS)], $statuses[array_rand($statuses)], $lenderIds, false);
            }
        }
    }

    private function insertLoan(int $borrowerId, float $amt, int $days, string $status, array $lenderIds, bool $withInvestment): void
    {
        $rate     = self::RATE;
        $fee      = round($amt * self::FEE_PCT, 2);
        $interest = round($amt * ($rate/100) * ($days/365), 2);
        $total    = $amt + $interest + $fee;
        $ref      = 'QS-'.strtoupper(Str::random(7));
        $created  = now()->subDays(rand(1,200));
        $approved = in_array($status,['marketplace','partially_funded','funded','active','disbursed','completed','defaulted','overdue']) ? $created->copy()->addDays(2) : null;
        $disbursed= in_array($status,['active','disbursed','completed','defaulted','overdue']) ? $created->copy()->addDays(5) : null;
        $funded   = in_array($status,['active','funded','disbursed','completed','defaulted']) ? $amt : (in_array($status,['partially_funded']) ? round($amt*0.5,2) : 0);

        $loanId = DB::table('loans')->insertGetId([
            'borrower_id'=>$borrowerId,'reference'=>$ref,'requested_amount'=>$amt,
            'approved_amount'=>$approved?$amt:null,'interest_rate'=>$rate,
            'platform_fee'=>$fee,'total_repayment'=>$total,
            'funded_amount'=>$funded,'loan_term_days'=>$days,
            'repayment_date'=>$approved?$approved->copy()->addDays($days)->toDateString():null,
            'status'=>$status,'risk_score'=>fake()->randomFloat(1,20,90),
            'submitted_at'=>$created,'approved_at'=>$approved,'disbursed_at'=>$disbursed,
            'completed_at'=>$status==='completed'?$disbursed?->copy()->addDays($days):null,
            'created_at'=>$created,'updated_at'=>now(),
            'external_loan_id'=>null,'external_provider'=>null,'sync_status'=>null,'last_synced_at'=>null,'external_metadata'=>null,
        ]);

        // Funding transactions
        if (in_array($status,['marketplace','partially_funded','funded','active','completed','defaulted'])) {
            $numFunders = $status==='partially_funded' ? 1 : rand(1,3);
            $fundPer    = round($funded / max(1,$numFunders), 2);
            $fStatus    = in_array($status,['active','completed','defaulted']) ? 'confirmed' : 'pending';
            for ($f=0; $f<$numFunders; $f++) {
                $lId = $lenderIds[array_rand($lenderIds)];
                $fAmt = ($f===$numFunders-1) ? $funded - ($fundPer*($numFunders-1)) : $fundPer;
                if ($fAmt <= 0) continue;
                $expReturn = round($fAmt*(1+($rate/100)*($days/365)),2);
                $ftId = DB::table('funding_transactions')->insertGetId([
                    'loan_id'=>$loanId,'lender_id'=>$lId,'amount'=>$fAmt,
                    'interest_rate'=>$rate,'expected_return'=>$expReturn,
                    'status'=>$fStatus,'transaction_reference'=>'FUND-'.strtoupper(Str::random(12)),
                    'confirmed_at'=>$fStatus==='confirmed'?now()->subDays(rand(1,30)):null,
                    'created_at'=>now()->subDays(rand(1,30)),'updated_at'=>now(),
                ]);
                // Investment record
                $invStatus = in_array($status,['completed']) ? 'completed' : (in_array($status,['cancelled']) ? 'cancelled' : 'active');
                $invId = DB::table('investments')->insertGetId([
                    'loan_id'=>$loanId,'lender_id'=>$lId,'amount'=>$fAmt,
                    'interest_rate'=>$rate,'expected_return'=>$expReturn,
                    'actual_return'=>$status==='completed'?$expReturn:0,
                    'status'=>$invStatus,
                    'funded_at'=>now()->subDays(rand(1,30)),
                    'completed_at'=>$status==='completed'?now()->subDays(rand(1,10)):null,
                    'created_at'=>now()->subDays(rand(1,30)),'updated_at'=>now(),
                ]);
                // Earnings for completed
                if ($status==='completed') {
                    DB::table('earnings')->insert([
                        'investment_id'=>$invId,'lender_id'=>$lId,'loan_id'=>$loanId,
                        'amount'=>round($expReturn-$fAmt,2),'type'=>'interest','status'=>'received',
                        'created_at'=>now()->subDays(rand(1,10)),'updated_at'=>now(),
                    ]);
                }
            }
        }

        // Repayments
        if (in_array($status,['active','completed','defaulted','overdue'])) {
            $repSt = match($status) { 'completed'=>'paid', 'defaulted'=>'defaulted', 'overdue'=>'overdue', default=>'pending' };
            DB::table('repayments')->insert([
                'loan_id'=>$loanId,'borrower_id'=>$borrowerId,
                'amount'=>$total,'principal'=>$amt,'interest'=>$interest,
                'penalty'=>$status==='defaulted'?round($total*0.05,2):0,
                'platform_fee'=>$fee,'status'=>$repSt,
                'due_date'=>now()->subDays(rand(0,30))->toDateString(),
                'paid_date'=>$repSt==='paid'?now()->subDays(rand(0,5))->toDateString():null,
                'days_overdue'=>in_array($repSt,['overdue','defaulted'])?rand(5,60):0,
                'transaction_reference'=>'REP-'.strtoupper(Str::random(12)),
                'payment_method'=>self::METHODS[array_rand(self::METHODS)],
                'created_at'=>now()->subDays(rand(1,30)),'updated_at'=>now(),
            ]);
        }
    }

    private function seedReferrals($clients): void
    {
        $this->command->info('Seeding referrals...');
        $ids = $clients->pluck('id')->toArray();
        $used = []; $rows = [];
        foreach ($clients->random(30) as $u) {
            $ref = $ids[array_rand($ids)];
            $key = $ref.'_'.$u->id;
            if ($ref===$u->id || isset($used[$key])) continue;
            $used[$key]=true;
            $rows[] = ['referrer_id'=>$ref,'referred_id'=>$u->id,'referral_code'=>strtoupper(Str::random(8)),
                'status'=>fake()->randomElement(['pending','completed','completed','completed']),
                'created_at'=>now()->subDays(rand(1,180)),'updated_at'=>now()];
        }
        if ($rows) DB::table('referrals')->insertOrIgnore($rows);
    }

    private function seedTrustHistory($clients): void
    {
        $this->command->info('Seeding trust score history...');
        $rows = [];
        foreach ($clients->random(30) as $u) {
            for ($i=0; $i<rand(2,5); $i++) {
                $delta = fake()->randomFloat(1,-5,10);
                $eventType = fake()->randomElement(['loan_repaid','kyc_approved','referral_bonus','loan_defaulted']);
                $rows[] = ['user_id'=>$u->id,
                    'previous_score'=>round(max(0,$u->trust_score-$delta),2),
                    'new_score'=>round((float)$u->trust_score,2),
                    'change'=>round($delta,2),
                    'reason'=>str_replace('_',' ',ucfirst($eventType)),
                    'event_type'=>$eventType,
                    'created_at'=>now()->subDays(rand(1,180)),'updated_at'=>now()];
            }
        }
        if ($rows) DB::table('trust_score_histories')->insert($rows);
    }

    private function seedAuditLogs($admin, $clients): void
    {
        $this->command->info('Seeding audit logs...');
        $events = ['login','kyc_approved','kyc_rejected','loan_approved','loan_rejected','loan_disbursed','repayment_confirmed','user_suspended'];
        $rows = [];
        foreach ($clients->random(20) as $u) {
            for ($i=0; $i<rand(1,4); $i++) {
                $rows[] = ['user_id'=>$u->id,'event'=>$events[array_rand($events)],
                    'auditable_type'=>'App\\Models\\User','auditable_id'=>$u->id,
                    'old_values'=>null,'new_values'=>null,
                    'ip_address'=>fake()->ipv4(),'user_agent'=>fake()->userAgent(),
                    'created_at'=>now()->subDays(rand(1,60)),'updated_at'=>now()];
            }
        }
        if ($rows) DB::table('audit_logs')->insert($rows);
    }

    private function truncateAll(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['earnings','investments','lender_repayments','repayments','collection_logs','collection_cases',
            'fraud_flags','reconciliation_logs','disbursement_transactions','funding_transactions','loans',
            'affordability_assessments','kyc_documents','kyc_submissions','trust_score_histories',
            'referrals','referral_codes','audit_logs','activity_logs','notifications',
            'login_attempts','user_sessions','phone_verifications','source_of_incomes','addresses',
            'personal_access_tokens','model_has_roles','model_has_permissions','users'] as $tbl) {
            DB::table($tbl)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
