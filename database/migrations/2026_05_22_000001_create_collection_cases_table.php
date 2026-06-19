<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->foreignId('borrower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->decimal('amount_outstanding', 10, 2)->default(0);
            $table->decimal('amount_recovered', 10, 2)->default(0);
            $table->enum('resolution', ['paid_in_full', 'partial_payment', 'payment_plan', 'written_off'])->nullable();
            $table->text('resolution_notes')->nullable();
            $table->integer('escalation_level')->default(0);
            $table->timestamp('last_contact_date')->nullable();
            $table->timestamp('next_action_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index('borrower_id');
            $table->index('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_cases');
    }
};
