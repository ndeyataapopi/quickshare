<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add new columns if they don't exist (for production DBs that ran original migration before update)
        if (! Schema::hasColumn('disbursement_transactions', 'payment_proof_path')) {
            Schema::table('disbursement_transactions', function (Blueprint $table) {
                $table->string('payment_proof_path')->nullable()->after('payment_method');
            });
        }

        if (! Schema::hasColumn('disbursement_transactions', 'borrower_confirmed_at')) {
            Schema::table('disbursement_transactions', function (Blueprint $table) {
                $table->timestamp('borrower_confirmed_at')->nullable()->after('reconciled_at');
            });
        }

        // Add new status to enum (MySQL only; SQLite doesn't enforce enum constraints)
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE disbursement_transactions MODIFY COLUMN status ENUM('awaiting_disbursement', 'processing', 'pending_borrower_confirmation', 'disbursed', 'failed', 'retried') DEFAULT 'awaiting_disbursement'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE disbursement_transactions MODIFY COLUMN status ENUM('awaiting_disbursement', 'processing', 'disbursed', 'failed', 'retried') DEFAULT 'awaiting_disbursement'");
        }

        Schema::table('disbursement_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('disbursement_transactions', 'payment_proof_path')) {
                $table->dropColumn('payment_proof_path');
            }
            if (Schema::hasColumn('disbursement_transactions', 'borrower_confirmed_at')) {
                $table->dropColumn('borrower_confirmed_at');
            }
        });
    }
};
