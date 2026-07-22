<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('disbursement_transactions', 'borrower_rejected_at')) {
            Schema::table('disbursement_transactions', function (Blueprint $table) {
                $table->timestamp('borrower_rejected_at')->nullable()->after('borrower_confirmed_at');
            });
        }

        if (! Schema::hasColumn('disbursement_transactions', 'rejection_reason')) {
            Schema::table('disbursement_transactions', function (Blueprint $table) {
                $table->text('rejection_reason')->nullable()->after('borrower_rejected_at');
            });
        }

        // Add new status to enum (MySQL only; SQLite doesn't enforce enum constraints)
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE disbursement_transactions MODIFY COLUMN status ENUM('awaiting_disbursement', 'processing', 'pending_borrower_confirmation', 'disbursed', 'failed', 'retried', 'rejected_by_borrower') DEFAULT 'awaiting_disbursement'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE disbursement_transactions MODIFY COLUMN status ENUM('awaiting_disbursement', 'processing', 'pending_borrower_confirmation', 'disbursed', 'failed', 'retried') DEFAULT 'awaiting_disbursement'");
        }

        Schema::table('disbursement_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('disbursement_transactions', 'borrower_rejected_at')) {
                $table->dropColumn('borrower_rejected_at');
            }
            if (Schema::hasColumn('disbursement_transactions', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }
        });
    }
};
