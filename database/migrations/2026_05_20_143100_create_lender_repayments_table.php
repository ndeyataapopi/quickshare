<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lender_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repayment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('funding_transaction_id')->constrained('funding_transactions')->cascadeOnDelete();
            
            // Proportional amounts
            $table->decimal('amount', 12, 2);                    // Total received by lender
            $table->decimal('principal_return', 12, 2);        // Principal portion
            $table->decimal('interest_earned', 12, 2);         // Interest earnings
            $table->decimal('penalty_share', 12, 2)->default(0); // Share of penalties (if any)
            
            // Funding proportion (for transparency)
            $table->decimal('funding_percentage', 5, 2);       // % of loan funded by this lender
            
            // Status
            $table->enum('status', ['pending', 'processed', 'failed'])->default('pending');
            $table->timestamp('processed_at')->nullable();
            
            // Ledger reference
            $table->string('transaction_reference', 32)->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['repayment_id', 'lender_id']);
            $table->index(['lender_id', 'status']);
            $table->index('funding_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lender_repayments');
    }
};
