<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add payment_proof_path column (if not already present)
        if (! Schema::hasColumn('repayments', 'payment_proof_path')) {
            Schema::table('repayments', function (Blueprint $table) {
                $table->string('payment_proof_path')->nullable()->after('payment_method');
            });
        }

        // Add 'pending_approval' to the status enum (MySQL only; SQLite doesn't enforce enums)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE repayments MODIFY status ENUM('pending','partial','paid','overdue','defaulted','pending_approval') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE repayments MODIFY status ENUM('pending','partial','paid','overdue','defaulted') NOT NULL DEFAULT 'pending'");
        }

        if (Schema::hasColumn('repayments', 'payment_proof_path')) {
            Schema::table('repayments', function (Blueprint $table) {
                $table->dropColumn('payment_proof_path');
            });
        }
    }
};
