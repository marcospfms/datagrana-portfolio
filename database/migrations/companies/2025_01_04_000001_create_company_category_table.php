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
        Schema::create('company_category', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coin_id');
            $table->string('name', 200);
            $table->string('short_name', 100);
            $table->string('reference', 30);
            $table->boolean('status')->default(true);
            $table->string('color_hex', 50)->nullable();
            $table->string('icon', 50)->nullable();
            $table->timestamps();

            $table->foreign('coin_id', 'fk_company_category_coins1')
                ->references('id')
                ->on('coins')
                ->onDelete('restrict')
                ->onUpdate('restrict');

            $table->index('coin_id', 'fk_company_category_coins1_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_category');
    }
};
