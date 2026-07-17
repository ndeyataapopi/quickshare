<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->string('agreement_path')->nullable()->after('repayment_date');
            $table->string('agreement_version', 20)->nullable()->after('agreement_path');
            $table->timestamp('agreement_generated_at')->nullable()->after('agreement_version');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn([
                'agreement_path',
                'agreement_version',
                'agreement_generated_at',
            ]);
        });
    }
};
