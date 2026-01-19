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
        Schema::create('installment_groups', function (Blueprint $table) {
            $table->id();
            $table->string('description', 300)->nullable();
            $table->decimal('value', 18, 8)->nullable();
            $table->date('purchase_date')->nullable();
            $table->integer('installments_count')->nullable();
            $table->timestamps();
        });

        Schema::create('installment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installment_group_id')->constrained('installment_groups')->cascadeOnDelete()->cascadeOnUpdate();
            $table->integer('order')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('installment_items');
        Schema::dropIfExists('installment_groups');
    }
};
