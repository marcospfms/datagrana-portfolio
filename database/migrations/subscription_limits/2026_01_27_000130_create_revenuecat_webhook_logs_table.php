<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenuecat_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 100);
            $table->string('app_user_id', 191)->nullable();
            $table->string('subscriber_id', 191)->nullable();
            $table->string('product_id', 100)->nullable();
            $table->string('entitlement_id', 100)->nullable();
            $table->string('store', 20)->nullable();
            $table->string('original_transaction_id', 191)->nullable();
            $table->json('payload');
            $table->enum('status', ['pending', 'processed', 'failed'])->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'idx_revenuecat_logs_status');
            $table->index('subscriber_id', 'idx_revenuecat_logs_subscriber');
            $table->index('app_user_id', 'idx_revenuecat_logs_app_user');
            $table->index('original_transaction_id', 'idx_revenuecat_logs_transaction');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenuecat_webhook_logs');
    }
};
