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
        Schema::table('company_splits', function (Blueprint $table) {
            $table->json('applied_closing_ids')->nullable()->after('history_applied');
            $table->json('applied_earning_ids')->nullable()->after('applied_closing_ids');
            $table->timestamp('applied_at')->nullable()->after('applied_earning_ids');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_splits', function (Blueprint $table) {
            $table->dropColumn(['applied_closing_ids', 'applied_earning_ids', 'applied_at']);
        });
    }
};
