<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('borrower_id')->constrained('users')->cascadeOnDelete();
            
            // Repayment details
            $table->decimal('amount', 12, 2);
            $table->decimal('principal', 12, 2)->nullable();      // Portion towards principal
            $table->decimal('interest', 12, 2)->nullable();     // Portion towards interest
            $table->decimal('penalty', 12, 2)->default(0);       // Late payment penalty
            $table->decimal('platform_fee', 12, 2)->default(0);  // Platform fee portion
            
            // Status tracking
            $table->enum('status', ['pending', 'partial', 'paid', 'overdue', 'defaulted', 'pending_approval', 'rejected'])->default('pending');
            
            // Schedule tracking
            $table->date('due_date');
            $table->date('paid_date')->nullable();
            $table->integer('days_overdue')->default(0);
            
            // Processing
            $table->string('transaction_reference', 32)->unique();
            $table->string('external_reference', 64)->nullable();
            $table->string('payment_method', 32)->default('bank_transfer');
            $table->string('payment_proof_path')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Indexes
            $table->index(['loan_id', 'status']);
            $table->index(['borrower_id', 'status']);
            $table->index(['status', 'due_date']);
            $table->index('due_date');
            $table->index('paid_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repayments');
    }
};
