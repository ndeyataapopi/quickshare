<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funding_transactions', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable()->after('confirmed_at');
        });

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE funding_transactions MODIFY COLUMN status ENUM('pending', 'confirmed', 'cancelled', 'rejected', 'refunded') DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE funding_transactions MODIFY COLUMN status ENUM('pending', 'confirmed', 'cancelled', 'refunded') DEFAULT 'pending'");
        }

        Schema::table('funding_transactions', function (Blueprint $table) {
            $table->dropColumn('rejected_at');
        });
    }
};
