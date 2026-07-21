<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disbursement_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            
            // Amounts
            $table->decimal('gross_amount', 12, 2);      // Total funded
            $table->decimal('platform_fee', 12, 2);    // Fee deducted
            $table->decimal('net_amount', 12, 2);      // To borrower
            
            // Status tracking
            $table->enum('status', ['awaiting_disbursement', 'processing', 'pending_borrower_confirmation', 'disbursed', 'failed', 'retried'])->default('awaiting_disbursement');
            
            // Processing
            $table->timestamp('processed_at')->nullable();
            $table->string('transaction_reference', 32)->unique();
            $table->string('external_reference', 64)->nullable()->index(); // Bank/payment provider reference
            $table->string('payment_method', 32)->default('bank_transfer');
            $table->string('payment_proof_path')->nullable();
            $table->text('failure_reason')->nullable();
            
            // Retry handling
            $table->integer('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            
            // Reconciliation
            $table->timestamp('reconciled_at')->nullable();
            $table->string('reconciled_by', 64)->nullable();
            $table->json('reconciliation_data')->nullable();
            $table->timestamp('borrower_confirmed_at')->nullable();
            
            // Ledger data
            $table->json('ledger_entries')->nullable();
            $table->text('notes')->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Indexes (external_reference already indexed at column definition)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disbursement_transactions');
    }
};
