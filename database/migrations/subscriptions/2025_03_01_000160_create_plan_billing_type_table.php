<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_billing_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plan')->cascadeOnDelete();
            $table->foreignId('billing_type_id')->constrained('billing_type')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['plan_id', 'billing_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_billing_type');
    }
};

