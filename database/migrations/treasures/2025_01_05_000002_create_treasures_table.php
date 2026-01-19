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
        Schema::create('treasures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('treasure_category_id');
            $table->string('name', 100);
            $table->dateTime('expiration_date')->comment('vencimento do título');
            $table->tinyInteger('status')->default(1);
            $table->tinyInteger('is_overdue')->default(0)->comment('true = vencido');
            $table->tinyInteger('can_buy')->default(1);
            $table->tinyInteger('can_sell')->default(1);
            $table->string('code', 15)->nullable()->comment('código identificador (se existir)');
            $table->decimal('last_unit_price', 18, 8)->nullable()->comment('ultimo preço unitário');
            $table->timestamp('last_unit_price_updated')->nullable()->comment('data que o preço unitário foi atualizado');
            $table->string('imported_with', 20)->nullable()->comment('identificador de onde foi extraído');
            $table->timestamps();

            // Índices e chaves estrangeiras
            $table->index('treasure_category_id', 'fk_treasures_treasure_category1_idx');
            $table->foreign('treasure_category_id', 'fk_treasures_treasure_category1')
                ->references('id')
                ->on('treasure_categories')
                ->onDelete('restrict')
                ->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treasures');
    }
};
