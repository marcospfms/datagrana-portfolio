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
        Schema::create('treasure_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coin_id');
            $table->string('name', 200);
            $table->string('short_name', 100);
            $table->string('reference', 30);
            $table->timestamp('list_updated_at')->nullable()->comment('saber a ultima data de atualização dos treasures daquela categoria para controle de rotinas');
            $table->tinyInteger('can_set_net_balance')->default(0)->comment('true = pode atualizar o valor do rendimento manualmente -> para títulos privados; -> não será focado inicialmente;');
            $table->string('color_hex', 50)->nullable();
            $table->string('icon', 50)->nullable();
            $table->timestamps();

            // Índices e chaves estrangeiras
            $table->index('coin_id', 'fk_treasure_category_coins1_idx');
            $table->foreign('coin_id', 'fk_treasure_category_coins1')
                ->references('id')
                ->on('coins')
                ->onDelete('restrict')
                ->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treasure_categories');
    }
};
