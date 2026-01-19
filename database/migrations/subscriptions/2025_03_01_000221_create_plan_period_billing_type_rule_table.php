<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_period_billing_type_rule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_period_id')->constrained('plan_period')->cascadeOnDelete();
            $table->foreignId('billing_type_id')->constrained('billing_type')->cascadeOnDelete();
            $table->enum('action', ['allow', 'deny']);
            $table->timestamps();

            $table->unique(['plan_period_id', 'billing_type_id'], 'unique_plan_period_billing_type_rule');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_period_billing_type_rule');
    }
};
