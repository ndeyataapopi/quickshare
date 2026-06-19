<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->string('external_loan_id')->nullable()->index()->after('reference');
            $table->string('external_provider')->nullable()->index()->after('external_loan_id');
            $table->string('sync_status', 32)->nullable()->index()->after('external_provider');
            $table->timestamp('last_synced_at')->nullable()->after('sync_status');
            $table->json('external_metadata')->nullable()->after('last_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn([
                'external_loan_id',
                'external_provider',
                'sync_status',
                'last_synced_at',
                'external_metadata',
            ]);
        });
    }
};
