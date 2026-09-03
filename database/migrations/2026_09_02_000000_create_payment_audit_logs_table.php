<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('operation', 64)->index();
            $table->string('payment_method', 64)->nullable();
            $table->string('provider', 64)->nullable();
            $table->string('transaction_type', 64)->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->string('transaction_reference', 64)->nullable()->index();
            $table->string('provider_reference', 128)->nullable()->index();
            $table->string('event', 64)->index();
            $table->string('status', 64)->nullable();
            $table->text('message')->nullable();
            $table->decimal('expected_amount', 14, 2)->nullable();
            $table->decimal('reported_amount', 14, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['operation', 'event']);
            $table->index(['provider', 'provider_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_audit_logs');
    }
};
