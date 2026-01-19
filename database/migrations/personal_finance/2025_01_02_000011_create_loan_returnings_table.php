<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('loan_returnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('movement_id')->nullable()->constrained('movements')->nullOnDelete()->cascadeOnUpdate();
            $table->decimal('scheduled_value', 18, 8)->nullable();
            $table->date('scheduled_date')->nullable();
            $table->string('description', 300)->nullable();
            $table->decimal('manual_value', 18, 8)->nullable();
            $table->date('manual_date')->nullable();
            $table->boolean('money_payment')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_returnings');
    }
};
