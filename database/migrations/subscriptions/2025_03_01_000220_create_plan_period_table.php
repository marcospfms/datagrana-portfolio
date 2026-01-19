<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_period', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plan')->cascadeOnDelete();
            $table->foreignId('subscription_period_id')->constrained('subscription_period')->cascadeOnDelete();
            $table->decimal('full_price', 18, 4)->default(0);
            $table->decimal('discount_price', 18, 4)->nullable();
            $table->decimal('setup_fee', 18, 4)->default(0);
            $table->foreignId('billing_type_id')->nullable()->constrained('billing_type')->nullOnDelete();
            $table->foreignId('gateway_billing_type_id')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('status')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['plan_id', 'subscription_period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_period');
    }
};
