<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('plan_period_id')->constrained('plan_period')->cascadeOnDelete();
            $table->foreignId('gateway_id')->nullable();
            $table->foreignId('billing_type_id')->nullable()->constrained('billing_type')->nullOnDelete();
            $table->foreignId('gateway_billing_type_id')->nullable();
            $table->foreignId('promotion_id')->nullable()->constrained('plan_promotion')->nullOnDelete();
            $table->string('status', 20)->default('trialing');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('renews_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->string('billing_email')->nullable();
            $table->timestamp('current_term_start')->nullable();
            $table->timestamp('current_term_end')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription');
    }
};
