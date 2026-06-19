<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->after('id');
            $table->string('last_name')->after('first_name');
            $table->string('national_id')->unique()->after('last_name');
            $table->string('phone')->unique()->after('email');
            $table->date('date_of_birth')->nullable()->after('phone');
            $table->string('referral_code', 10)->unique()->after('password');
            $table->foreignId('referred_by')->nullable()->after('referral_code')
                ->constrained('users')->nullOnDelete();
            $table->decimal('trust_score', 5, 2)->default(50.00)->after('referred_by');
            $table->enum('status', ['pending', 'active', 'suspended', 'deactivated'])
                ->default('pending')->after('trust_score');
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['referred_by']);
            $table->dropColumn([
                'first_name',
                'last_name',
                'national_id',
                'phone',
                'date_of_birth',
                'referral_code',
                'referred_by',
                'trust_score',
                'status',
                'phone_verified_at',
            ]);
            $table->string('name')->after('id');
        });
    }
};
