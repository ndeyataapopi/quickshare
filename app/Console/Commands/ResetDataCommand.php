<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetDataCommand extends Command
{
    protected $signature = 'quickshare:reset-data
                            {--force : Skip confirmation prompt}';

    protected $description = 'Remove all transactional/business data while preserving users, admin accounts, roles, permissions, KYC, and configuration.';

    /**
     * Tables to truncate, grouped by deletion order.
     * Group 1: Loan-dependent transactional tables (children first).
     * Group 2: Log / audit / notification / session / temp tables.
     * Group 3: Infrastructure tables (cache, queue, jobs).
     *
     * Each table is keyed by its label for display. The order within
     * and across groups respects foreign-key dependencies.
     */
    protected array $transactionalTables = [
        'Loan & Investment Transactional Data' => [
            'earnings',
            'lender_repayments',
            'collection_logs',
            'investments',
            'repayments',
            'funding_transactions',
            'disbursement_transactions',
            'collection_cases',
            'affordability_assessments',
            'reconciliation_logs',
            'loans',
        ],
        'Fraud & Trust History' => [
            'fraud_flags',
            'trust_score_histories',
        ],
        'Logs, Audit & Notifications' => [
            'activity_logs',
            'audit_logs',
            'notifications',
        ],
        'Security, Sessions & Verification' => [
            'login_attempts',
            'user_sessions',
            'phone_verifications',
            'personal_access_tokens',
            'password_reset_tokens',
            'sessions',
        ],
        'Cache' => [
            'cache',
            'cache_locks',
        ],
        'Queue & Jobs' => [
            'jobs',
            'job_batches',
            'failed_jobs',
        ],
    ];

    /**
     * Tables that are preserved (not deleted).
     */
    protected array $preservedTables = [
        'users',
        'addresses',
        'source_of_incomes',
        'referral_codes',
        'referrals',
        'kyc_submissions',
        'kyc_documents',
        'roles',
        'permissions',
        'model_has_roles',
        'model_has_permissions',
        'role_has_permissions',
        'migrations',
    ];

    public function handle(): int
    {
        $this->info('QuickShare Data Reset — Friends & Family Private Beta');
        $this->line('');

        $this->displayPlan();

        if (! $this->option('force') && ! $this->confirm('This will permanently delete all transactional data. Do you wish to continue?')) {
            $this->warn('Aborted. No data was modified.');

            return Command::SUCCESS;
        }

        $deleted = $this->executeDeletes();

        $this->line('');
        $this->displaySummary($deleted);

        return Command::SUCCESS;
    }

    protected function displayPlan(): void
    {
        $this->info('The following data will be DELETED:');

        foreach ($this->transactionalTables as $group => $tables) {
            $this->line("  {$group}:");
            foreach ($tables as $table) {
                $count = $this->tableExists($table) ? $this->getRowCount($table) : 'N/A';
                $this->line("    - {$table} ({$count} rows)");
            }
        }

        $this->line('');
        $this->info('The following data will be PRESERVED:');
        foreach ($this->preservedTables as $table) {
            $count = $this->tableExists($table) ? $this->getRowCount($table) : 'N/A';
            $this->line("    - {$table} ({$count} rows)");
        }
    }

    protected function executeDeletes(): array
    {
        $deleted = [];
        $skipped = [];

        foreach ($this->transactionalTables as $group => $tables) {
            $this->line('');
            $this->info("Processing: {$group}");

            DB::transaction(function () use ($tables, &$deleted, &$skipped): void {
                foreach ($tables as $table) {
                    if (! $this->tableExists($table)) {
                        $skipped[] = $table;
                        $this->warn("  Skipped: {$table} (table does not exist)");
                        continue;
                    }

                    $count = $this->getRowCount($table);

                    if ($count === 0) {
                        $deleted[$table] = 0;
                        $this->line("  {$table}: 0 rows (already empty)");
                        continue;
                    }

                    $deleted[$table] = DB::table($table)->delete();
                    $this->line("  {$table}: {$deleted[$table]} rows deleted");
                }
            });
        }

        $this->resetAutoIncrements($deleted);

        return ['deleted' => $deleted, 'skipped' => $skipped];
    }

    protected function resetAutoIncrements(array $deletedTables): void
    {
        $driver = DB::getDriverName();

        $tables = array_keys(array_filter($deletedTables, fn ($count) => $count >= 0));

        if ($driver === 'sqlite') {
            foreach ($tables as $table) {
                if (! $this->tableExists($table)) {
                    continue;
                }
                DB::statement("DELETE FROM sqlite_sequence WHERE name = ?", [$table]);
            }
        } elseif ($driver === 'mysql') {
            foreach ($tables as $table) {
                if (! $this->tableExists($table)) {
                    continue;
                }
                DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
            }
        }
    }

    protected function displaySummary(array $result): void
    {
        $deleted = $result['deleted'];
        $skipped = $result['skipped'];

        $this->info('========================================');
        $this->info('         DATA RESET SUMMARY');
        $this->info('========================================');

        $this->line('');
        $this->info('Rows Deleted:');
        $rows = [];
        $totalDeleted = 0;
        foreach ($deleted as $table => $count) {
            $rows[] = [$table, $count];
            $totalDeleted += $count;
        }
        $rows[] = ['', ''];
        $rows[] = ['TOTAL', $totalDeleted];
        $this->table(['Table', 'Rows Deleted'], $rows);

        $this->line('');
        $this->info('Rows Preserved:');
        $preservedRows = [];
        foreach ($this->preservedTables as $table) {
            $count = $this->tableExists($table) ? $this->getRowCount($table) : 'N/A';
            $preservedRows[] = [$table, $count];
        }
        $this->table(['Table', 'Rows Preserved'], $preservedRows);

        if (! empty($skipped)) {
            $this->line('');
            $this->warn('Skipped Tables (do not exist):');
            foreach ($skipped as $table) {
                $this->line("  - {$table}");
            }
        }

        $this->line('');
        $this->info('Auto-increment sequences have been reset.');
        $this->info('Data reset complete.');
    }

    protected function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }

    protected function getRowCount(string $table): int
    {
        return DB::table($table)->count();
    }
}
