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
        Schema::create('treasure_transaction', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consolidated_id');
            $table->date('date');
            $table->enum('operation', ['C', 'V'])->comment('C = Compra, V = Venda');
            $table->decimal('invested_value', 18, 8);
            $table->decimal('quantity', 18, 8);
            $table->decimal('price', 18, 8)->nullable()->comment('Preço no dia da compra/venda do ativo: Valor Investido / Quantidade de cotas - Calculated on insert');
            $table->string('imported_with', 20)->default('Manual');
            $table->timestamps();

            // Index
            $table->index('consolidated_id', 'fk_treasure_transaction_consolidated1_idx');

            // Foreign key
            $table->foreign('consolidated_id', 'fk_treasure_transaction_consolidated1')
                ->references('id')
                ->on('consolidated')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treasure_transaction');
    }
};
