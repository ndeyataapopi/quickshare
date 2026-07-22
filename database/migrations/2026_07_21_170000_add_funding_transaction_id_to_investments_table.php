<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->foreignId('funding_transaction_id')
                ->nullable()
                ->after('lender_id')
                ->constrained('funding_transactions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropForeign(['funding_transaction_id']);
            $table->dropColumn('funding_transaction_id');
        });
    }
};
