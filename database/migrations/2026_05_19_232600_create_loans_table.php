<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrower_id')->constrained('users')->cascadeOnDelete();
            $table->string('reference', 20)->unique();
            $table->decimal('requested_amount', 12, 2);
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->decimal('interest_rate', 5, 2);
            $table->decimal('platform_fee', 12, 2)->default(0);
            $table->decimal('total_repayment', 12, 2);
            $table->decimal('funded_amount', 12, 2)->default(0);
            $table->date('repayment_date')->nullable();
            $table->unsignedInteger('loan_term_days');
            $table->enum('status', [
                'draft',
                'pending_review',
                'marketplace',
                'partially_funded',
                'funded',
                'awaiting_disbursement',
                'disbursed',
                'active',
                'overdue',
                'completed',
                'defaulted',
                'cancelled',
            ])->default('draft');
            $table->decimal('risk_score', 5, 2)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['borrower_id', 'status']);
            $table->index('status');
            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
