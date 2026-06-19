<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email', 255)->index();
            $table->string('ip_address', 45)->index();
            $table->text('user_agent');
            $table->boolean('success')->default(false)->index();
            $table->string('failure_reason')->nullable();
            $table->string('location_country', 100)->nullable();
            $table->string('location_city', 100)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('created_at')->index();
            $table->timestamp('updated_at')->nullable();

            $table->index(['email', 'success', 'created_at']);
            $table->index(['ip_address', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
