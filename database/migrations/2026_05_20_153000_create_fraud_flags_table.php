<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fraud_flags', function (Blueprint $table) {
            $table->id();
            
            // Subject (polymorphic)
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            
            // Flag details
            $table->enum('flag_type', [
                'duplicate_identity',
                'duplicate_bank_account',
                'suspicious_funding_pattern',
                'rapid_registration',
                'fake_referral',
                'multiple_loans_same_day',
                'rapid_loan_sequence',
                'high_velocity_borrowing',
                'referral_abuse',
                'location_anomaly',
                'device_fingerprint_mismatch',
            ]);
            
            $table->enum('severity', ['low', 'medium', 'high', 'critical']);
            $table->enum('status', ['open', 'under_review', 'confirmed', 'false_positive', 'resolved'])->default('open');
            
            // Description and evidence
            $table->text('description');
            $table->json('evidence')->nullable();
            $table->json('related_entities')->nullable();
            
            // Risk scoring
            $table->integer('risk_score')->default(0); // 0-100
            
            // Review tracking
            $table->foreignId('detected_by')->constrained('users'); // System or admin
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->text('resolution_notes')->nullable();
            
            // Actions taken
            $table->json('actions_taken')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['subject_type', 'subject_id']);
            $table->index(['flag_type', 'status']);
            $table->index(['severity', 'status']);
            $table->index('status');
            $table->index('risk_score');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_flags');
    }
};
