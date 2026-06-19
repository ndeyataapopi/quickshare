<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->nullable()->constrained('loans')->cascadeOnDelete();
            $table->string('external_loan_id')->nullable();
            $table->string('provider', 32)->default('mifos');
            $table->string('operation', 64); // create, update, status_sync, reconcile
            $table->string('direction', 16); // outbound, inbound
            $table->string('status', 32)->default('pending'); // pending, success, failed, skipped
            $table->text('request_payload')->nullable();
            $table->text('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('http_status')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['loan_id', 'operation']);
            $table->index(['provider', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_logs');
    }
};
