<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 64)->index();
            $table->string('provider_event_id', 128)->nullable();
            $table->string('provider_reference', 128)->nullable()->index();
            $table->string('event_type', 64)->nullable();
            $table->json('payload');
            $table->string('signature', 512)->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->string('status', 32)->default('received');
            $table->string('transaction_type', 64)->nullable();
            $table->foreignId('transaction_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_event_id'], 'payment_webhook_events_provider_event_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
    }
};
