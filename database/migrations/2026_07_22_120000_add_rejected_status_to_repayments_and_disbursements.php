<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add 'rejected' to the repayments status enum (MySQL only; SQLite doesn't enforce enums)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE repayments MODIFY status ENUM('pending','partial','paid','overdue','defaulted','pending_approval','rejected') NOT NULL DEFAULT 'pending'");
        }

        // Add 'confirmed' and 'rejected' to disbursement_transactions status enum
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE disbursement_transactions MODIFY status ENUM('awaiting_disbursement','processing','pending_borrower_confirmation','disbursed','failed','retried','rejected_by_borrower','awaiting_approval','confirmed','rejected') NOT NULL DEFAULT 'awaiting_disbursement'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE repayments MODIFY status ENUM('pending','partial','paid','overdue','defaulted','pending_approval') NOT NULL DEFAULT 'pending'");
            DB::statement("ALTER TABLE disbursement_transactions MODIFY status ENUM('awaiting_disbursement','processing','pending_borrower_confirmation','disbursed','failed','retried','rejected_by_borrower','awaiting_approval') NOT NULL DEFAULT 'awaiting_disbursement'");
        }
    }
};
