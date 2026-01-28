<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plan_config', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->boolean('status')->default(true);
            $table->string('config_key', 50);
            $table->integer('config_value')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();

            $table->unique(['subscription_plan_id', 'config_key'], 'idx_plan_config_unique');
            $table->index('config_key', 'idx_plan_config_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plan_config');
    }
};
