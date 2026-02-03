<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('renewal_count')->default(0)->after('revenuecat_store');
            $table->dropColumn('revenuecat_raw_data');
        });
    }

    public function down(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->json('revenuecat_raw_data')->nullable()->after('revenuecat_store');
            $table->dropColumn('renewal_count');
        });
    }
};
