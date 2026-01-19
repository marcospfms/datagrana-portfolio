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
        Schema::create('recurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('movement_category_id')->nullable()->constrained('movement_category')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('debit_id')->nullable()->constrained('debits')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('credit_id')->nullable()->constrained('credits')->nullOnDelete()->cascadeOnUpdate();
            $table->enum('type', ['monthly_fixed_day', 'weekly_fixed_day', 'continuous_period']);
            $table->integer('type_param');
            $table->date('next_date')->nullable();
            $table->string('movement_description', 300);
            $table->enum('movement_type', ['income', 'expense']);
            $table->decimal('movement_value', 18, 8);
            $table->text('movement_obs')->nullable();
            $table->timestamps();
        });

        Schema::table('movements', function (Blueprint $table) {
            $table->foreign('recurrence_id')->references('id')->on('recurrences')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurrences');
    }
};
