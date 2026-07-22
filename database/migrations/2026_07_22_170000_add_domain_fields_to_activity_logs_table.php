<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreignId('actor_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->foreignId('loan_id')->nullable()->after('subject_id')->constrained('loans')->nullOnDelete();
            $table->foreignId('investment_id')->nullable()->after('loan_id')->constrained('investments')->nullOnDelete();
            $table->foreignId('repayment_id')->nullable()->after('investment_id')->constrained('repayments')->nullOnDelete();
            $table->foreignId('funding_transaction_id')->nullable()->after('repayment_id')->constrained('funding_transactions')->nullOnDelete();
            $table->foreignId('disbursement_transaction_id')->nullable()->after('funding_transaction_id')->constrained('disbursement_transactions')->nullOnDelete();
            $table->decimal('amount', 14, 2)->nullable()->after('disbursement_transaction_id');
            $table->string('previous_status', 50)->nullable()->after('amount');
            $table->string('new_status', 50)->nullable()->after('previous_status');

            $table->index('actor_id');
            $table->index('loan_id');
            $table->index('repayment_id');
            $table->index('funding_transaction_id');
            $table->index('disbursement_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['actor_id']);
            $table->dropIndex(['loan_id']);
            $table->dropIndex(['repayment_id']);
            $table->dropIndex(['funding_transaction_id']);
            $table->dropIndex(['disbursement_transaction_id']);

            $table->dropColumn([
                'actor_id',
                'loan_id',
                'investment_id',
                'repayment_id',
                'funding_transaction_id',
                'disbursement_transaction_id',
                'amount',
                'previous_status',
                'new_status',
            ]);
        });
    }
};
