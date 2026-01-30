<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('revenuecat_webhook_logs', function (Blueprint $table) {
            $table->foreignId('user_subscription_id')
                ->nullable()
                ->after('original_transaction_id')
                ->constrained('user_subscriptions')
                ->nullOnDelete();

            $table->index('user_subscription_id', 'idx_revenuecat_logs_subscription');
        });
    }

    public function down(): void
    {
        Schema::table('revenuecat_webhook_logs', function (Blueprint $table) {
            $table->dropIndex('idx_revenuecat_logs_subscription');
            $table->dropConstrainedForeignId('user_subscription_id');
        });
    }
};
