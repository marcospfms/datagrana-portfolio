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
        Schema::create('movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movement_category_id')->nullable()->constrained('movement_category')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('debit_id')->nullable()->constrained('debits')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('credit_closing_id')->nullable()->constrained('credit_closings')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('installment_item_id')->nullable()->constrained('installment_items')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('recurrence_id')->nullable();
            $table->string('description', 300);
            $table->date('date');
            $table->enum('type', ['income', 'expense']);
            $table->decimal('value', 18, 8);
            $table->decimal('paid_value', 18, 8)->nullable();
            $table->text('obs')->nullable();
            $table->timestamps();

            $table->index('date', 'idx_movement_date1');
            $table->index(['description'], 'idx_movements_description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movements');
    }
};
