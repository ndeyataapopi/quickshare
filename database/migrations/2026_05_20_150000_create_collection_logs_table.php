<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('borrower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('repayment_id')->nullable()->constrained()->nullOnDelete();
            
            // Collection activity
            $table->enum('action_type', [
                'reminder_sent',
                'reminder_delivered',
                'reminder_failed',
                'escalation_level_1',
                'escalation_level_2',
                'escalation_level_3',
                'contact_attempted',
                'contact_made',
                'payment_received',
                'default_initiated',
                'default_processed',
                'referral_notified',
                'legal_referral',
            ]);
            
            // Channel (SMS, WhatsApp, Email, Call)
            $table->string('channel', 32)->nullable();
            $table->string('channel_provider', 32)->nullable(); // Twilio, SendGrid, etc.
            
            // Content
            $table->text('message')->nullable();
            $table->string('template_used', 64)->nullable();
            
            // Status tracking
            $table->enum('status', ['pending', 'sent', 'delivered', 'failed', 'bounced'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            
            // Response tracking
            $table->boolean('response_received')->default(false);
            $table->text('response_content')->nullable();
            $table->timestamp('responded_at')->nullable();
            
            // Metadata
            $table->string('external_reference', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Indexes
            $table->index(['loan_id', 'action_type']);
            $table->index(['borrower_id', 'action_type']);
            $table->index(['status', 'created_at']);
            $table->index(['action_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_logs');
    }
};
