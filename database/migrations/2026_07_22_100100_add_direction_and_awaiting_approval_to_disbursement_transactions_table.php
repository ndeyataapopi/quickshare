<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add direction column (if not already present)
        if (! Schema::hasColumn('disbursement_transactions', 'direction')) {
            Schema::table('disbursement_transactions', function (Blueprint $table) {
                $table->enum('direction', ['incoming', 'outgoing'])->default('outgoing')->after('loan_id');
                $table->index('direction');
            });
        }

        // Add 'awaiting_approval' to the status enum (MySQL only; SQLite doesn't enforce enums)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE disbursement_transactions MODIFY status ENUM('awaiting_disbursement','processing','pending_borrower_confirmation','disbursed','failed','retried','rejected_by_borrower','awaiting_approval') NOT NULL DEFAULT 'awaiting_disbursement'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE disbursement_transactions MODIFY status ENUM('awaiting_disbursement','processing','pending_borrower_confirmation','disbursed','failed','retried','rejected_by_borrower') NOT NULL DEFAULT 'awaiting_disbursement'");
        }

        if (Schema::hasColumn('disbursement_transactions', 'direction')) {
            Schema::table('disbursement_transactions', function (Blueprint $table) {
                $table->dropIndex(['direction']);
                $table->dropColumn('direction');
            });
        }
    }
};
