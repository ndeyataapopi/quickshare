<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funding_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lender_id')->constrained('users')->cascadeOnDelete();
            
            // Funding details
            $table->decimal('amount', 12, 2);
            $table->decimal('interest_rate', 5, 2); // Snapshot at time of funding
            $table->decimal('expected_return', 12, 2)->nullable(); // Calculated return
            
            // Status tracking
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'refunded'])->default('pending');
            
            // Processing
            $table->timestamp('confirmed_at')->nullable();
            $table->string('transaction_reference', 32)->unique();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Indexes
            $table->index(['loan_id', 'status']);
            $table->index(['lender_id', 'status']);
            $table->index(['loan_id', 'lender_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funding_transactions');
    }
};
