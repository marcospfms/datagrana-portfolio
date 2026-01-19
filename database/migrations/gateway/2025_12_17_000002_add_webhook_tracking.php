<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('gateway_webhook_log', function (Blueprint $table) {
            $table->unsignedInteger('received_count')->default(1)->after('webhook_id');
            $table->timestamp('first_received_at')->nullable()->after('received_count');
            $table->timestamp('last_received_at')->nullable()->after('first_received_at');
        });


        Schema::table('gateway_webhook_log', function (Blueprint $table) {
            $table->unique(['gateway_id', 'webhook_id']);
        });
    }

    public function down(): void
    {
        Schema::table('gateway_webhook_log', function (Blueprint $table) {
            $table->dropUnique(['gateway_id', 'webhook_id']);
            $table->dropColumn(['received_count', 'first_received_at', 'last_received_at']);
        });
    }
};
