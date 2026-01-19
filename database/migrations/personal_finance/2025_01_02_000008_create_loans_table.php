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
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movement_id')->nullable()->constrained('movements')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('person_id')->constrained('persons')->cascadeOnDelete()->cascadeOnUpdate();
            $table->date('date')->nullable();
            $table->decimal('value', 18, 8)->nullable();
            $table->string('description', 300)->nullable();
            $table->decimal('balance', 18, 8);
            $table->enum('type', ['taken', 'given'])->default('given');
            $table->date('paid_off_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
