<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('revenuecat_webhook_logs', function (Blueprint $table) {
            $table->string('event_id', 191)->nullable()->after('id');
            $table->unique('event_id', 'uniq_revenuecat_event_id');
        });
    }

    public function down(): void
    {
        Schema::table('revenuecat_webhook_logs', function (Blueprint $table) {
            $table->dropUnique('uniq_revenuecat_event_id');
            $table->dropColumn('event_id');
        });
    }
};
