<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funding_transactions', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('status');
            $table->string('payment_method_detail')->nullable()->after('payment_method');
            $table->string('payment_reference')->nullable()->after('transaction_reference');
            $table->string('payment_proof_path')->nullable()->after('payment_reference');
            $table->timestamp('payment_date')->nullable()->after('payment_proof_path');
            $table->timestamp('admin_verified_at')->nullable()->after('payment_date');
            $table->foreignId('admin_verified_by')->nullable()->constrained('users')->nullOnDelete()->after('admin_verified_at');
            $table->text('admin_notes')->nullable()->after('admin_verified_by');

            $table->index('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('funding_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'payment_method',
                'payment_method_detail',
                'payment_reference',
                'payment_proof_path',
                'payment_date',
                'admin_verified_at',
                'admin_verified_by',
                'admin_notes',
            ]);
        });
    }
};
