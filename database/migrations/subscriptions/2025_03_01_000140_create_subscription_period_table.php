<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_period', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('months');
            $table->string('currency_code', 3);
            $table->unsignedInteger('trial_days')->default(0);
            $table->unsignedInteger('grace_days')->default(0);
            $table->unsignedInteger('installments_allowed')->default(1);
            $table->boolean('is_recurring')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('status')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_period');
    }
};
