<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code', 10)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('usage_count')->default(0);
            $table->unsignedInteger('max_uses')->nullable();
            $table->timestamps();

            $table->index(['code', 'is_active']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_codes');
    }
};
