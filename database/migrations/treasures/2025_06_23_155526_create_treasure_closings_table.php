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
        Schema::create('treasure_closings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('treasure_id');
            $table->date('date');
            $table->decimal('purchase_tax', 18, 8);
            $table->decimal('sales_tax', 18, 8);
            $table->decimal('purchase_price', 18, 8);
            $table->decimal('sales_price', 18, 8);
            $table->decimal('base_price', 18, 8);
            $table->timestamps();

            // Unique index for treasure_id and date
            $table->unique(['treasure_id', 'date'], 'unique_treasure_date');

            // Foreign key constraint
            $table->foreign('treasure_id', 'fk_treasure_closings_treasures1')
                ->references('id')
                ->on('treasures')
                ->onDelete('no action')
                ->onUpdate('no action');

            // Index for treasure_id
            $table->index('treasure_id', 'fk_treasure_closings_treasures1_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treasure_closings');
    }
};
