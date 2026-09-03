<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'funding_transactions',
            'disbursement_transactions',
            'repayments',
            'lender_repayments',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                if (! Schema::hasColumn($table->getTable(), 'execution_mode')) {
                    $table->string('execution_mode', 32)->nullable()->after('status');
                }
                if (! Schema::hasColumn($table->getTable(), 'provider')) {
                    $table->string('provider', 64)->nullable()->after('execution_mode');
                }
                if (! Schema::hasColumn($table->getTable(), 'provider_reference')) {
                    $table->string('provider_reference', 128)->nullable()->index()->after('provider');
                }
                if (! Schema::hasColumn($table->getTable(), 'provider_status')) {
                    $table->string('provider_status', 64)->nullable()->after('provider_reference');
                }
                if (! Schema::hasColumn($table->getTable(), 'provider_metadata')) {
                    $table->json('provider_metadata')->nullable()->after('provider_status');
                }
                if (! Schema::hasColumn($table->getTable(), 'provider_error_code')) {
                    $table->string('provider_error_code', 64)->nullable()->after('provider_metadata');
                }
                if (! Schema::hasColumn($table->getTable(), 'payment_link_url')) {
                    $table->text('payment_link_url')->nullable()->after('provider_error_code');
                }
                if (! Schema::hasColumn($table->getTable(), 'payment_link_expires_at')) {
                    $table->timestamp('payment_link_expires_at')->nullable()->after('payment_link_url');
                }
                if (! Schema::hasColumn($table->getTable(), 'payout_method')) {
                    $table->string('payout_method', 32)->nullable()->after('payment_link_expires_at');
                }
                if ($table->getTable() === 'lender_repayments' && ! Schema::hasColumn($table->getTable(), 'payment_method')) {
                    $table->string('payment_method', 32)->nullable()->after('payout_method');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'funding_transactions',
            'disbursement_transactions',
            'repayments',
            'lender_repayments',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $columns = [
                    'execution_mode',
                    'provider',
                    'provider_reference',
                    'provider_status',
                    'provider_metadata',
                    'provider_error_code',
                    'payment_link_url',
                    'payment_link_expires_at',
                    'payout_method',
                ];

                if ($tableName === 'lender_repayments') {
                    $columns[] = 'payment_method';
                }

                $existing = Schema::getColumnListing($tableName);
                $toDrop = array_intersect($columns, $existing);

                if (! empty($toDrop)) {
                    $table->dropColumn($toDrop);
                }
            });
        }
    }
};
