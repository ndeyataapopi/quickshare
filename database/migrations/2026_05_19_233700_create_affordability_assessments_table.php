<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affordability_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();

            // Input data snapshot
            $table->decimal('monthly_income', 12, 2);
            $table->decimal('monthly_expenses', 12, 2)->default(0);
            $table->decimal('existing_debt', 12, 2)->default(0);
            $table->decimal('monthly_debt_repayments', 12, 2)->default(0);
            $table->decimal('payslip_gross', 12, 2)->nullable();
            $table->decimal('payslip_net', 12, 2)->nullable();
            $table->decimal('bank_avg_balance', 12, 2)->nullable();
            $table->decimal('bank_avg_income', 12, 2)->nullable();
            $table->decimal('bank_avg_expenses', 12, 2)->nullable();

            // Calculated metrics
            $table->decimal('debt_to_income_ratio', 5, 2);
            $table->decimal('disposable_income', 12, 2);
            $table->decimal('affordability_score', 5, 2);
            $table->decimal('max_loan_amount', 12, 2);
            $table->decimal('max_monthly_repayment', 12, 2);

            // Trust score at time of assessment
            $table->decimal('trust_score', 5, 2);
            $table->string('trust_tier', 20);

            // Repayment history metrics
            $table->unsignedInteger('total_loans')->default(0);
            $table->unsignedInteger('completed_loans')->default(0);
            $table->unsignedInteger('defaulted_loans')->default(0);
            $table->unsignedInteger('late_repayments')->default(0);
            $table->decimal('repayment_reliability', 5, 2)->default(0);

            // Result
            $table->string('risk_classification', 20);
            $table->enum('decision', ['approve', 'reject', 'manual_review'])->default('manual_review');
            $table->text('decision_reasons')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('loan_id');
            $table->index('decision');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affordability_assessments');
    }
};
