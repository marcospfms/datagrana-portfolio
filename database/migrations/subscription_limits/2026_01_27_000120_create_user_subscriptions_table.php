<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->string('plan_name', 100);
            $table->string('plan_slug', 50);
            $table->decimal('price_monthly', 10, 2);
            $table->json('limits_snapshot')->nullable();
            $table->json('features_snapshot')->nullable();
            $table->enum('status', ['active', 'expired', 'canceled', 'trialing', 'pending'])->default('active');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('renews_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->string('revenuecat_subscriber_id', 191)->nullable();
            $table->string('revenuecat_original_transaction_id', 191)->nullable();
            $table->string('revenuecat_product_id', 100)->nullable();
            $table->string('revenuecat_entitlement_id', 100)->nullable();
            $table->string('revenuecat_store', 20)->nullable();
            $table->json('revenuecat_raw_data')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'ends_at'], 'idx_user_subscription_active');
            $table->index(['renews_at', 'status'], 'idx_user_subscription_renewal');
            $table->index('revenuecat_subscriber_id', 'idx_user_subscription_subscriber');
            $table->unique('revenuecat_original_transaction_id', 'idx_user_subscription_transaction');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
    }
};
