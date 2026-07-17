<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->json('configuration_snapshot')->nullable()->after('agreement_generated_at');
            $table->json('agreement_consent')->nullable()->after('configuration_snapshot');
            $table->string('agreement_ip_address', 45)->nullable()->after('agreement_consent');
            $table->text('agreement_user_agent')->nullable()->after('agreement_ip_address');
            $table->timestamp('agreement_consented_at')->nullable()->after('agreement_user_agent');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn([
                'configuration_snapshot',
                'agreement_consent',
                'agreement_ip_address',
                'agreement_user_agent',
                'agreement_consented_at',
            ]);
        });
    }
};
