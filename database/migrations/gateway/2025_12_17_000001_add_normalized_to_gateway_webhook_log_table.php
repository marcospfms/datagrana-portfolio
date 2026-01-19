<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gateway_webhook_log', function (Blueprint $table) {
            $table->json('normalized')->nullable()->after('payload');
        });
    }

    public function down(): void
    {
        Schema::table('gateway_webhook_log', function (Blueprint $table) {
            $table->dropColumn('normalized');
        });
    }
};

