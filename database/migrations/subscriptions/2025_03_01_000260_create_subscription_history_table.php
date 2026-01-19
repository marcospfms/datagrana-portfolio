<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscription')->cascadeOnDelete();
            $table->foreignId('from_plan_period_id')->nullable()->constrained('plan_period')->nullOnDelete();
            $table->foreignId('to_plan_period_id')->nullable()->constrained('plan_period')->nullOnDelete();
            $table->string('change_type', 20);
            $table->decimal('prorate_credit', 18, 4)->default(0);
            $table->string('reason')->nullable();
            $table->timestamp('changed_at')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_history');
    }
};
