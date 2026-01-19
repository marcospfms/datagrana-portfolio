<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('credit_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->date('start_date');
            $table->date('end_date');
            $table->date('due_date');
            $table->decimal('balance', 18, 8)->default(0);
            $table->timestamps();

            $table->index(['credit_id', 'start_date', 'end_date'], 'idx_credit_closing_dates');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_closings');
    }
};
