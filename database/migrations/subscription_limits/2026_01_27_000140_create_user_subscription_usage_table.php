<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_subscription_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_subscription_id')->constrained('user_subscriptions')->cascadeOnDelete();
            $table->integer('current_portfolios')->default(0);
            $table->integer('current_compositions')->default(0);
            $table->integer('current_positions')->default(0);
            $table->integer('current_accounts')->default(0);
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();

            $table->unique('user_id', 'idx_user_subscription_usage_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_subscription_usage');
    }
};
