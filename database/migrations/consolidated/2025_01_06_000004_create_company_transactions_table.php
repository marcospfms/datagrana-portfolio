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
        Schema::create('company_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consolidated_id');
            $table->dateTime('date')->comment('Data e Hora da operação');
            $table->enum('operation', ['C', 'V'])->comment('C = Compra, V = Venda');
            $table->decimal('quantity', 18, 8)->comment('Quantidade');
            $table->decimal('price', 18, 8)->comment('Preço pago na unidade');
            $table->decimal('total_value', 18, 8)->comment('Preço total da transação');
            $table->string('imported_with', 20)->default('Manual');
            $table->timestamps();

            // Index
            $table->index('consolidated_id', 'fk_company_transactions_consolidated1_idx');

            // Foreign key
            $table->foreign('consolidated_id', 'fk_company_transactions_consolidated1')
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
        Schema::dropIfExists('company_transactions');
    }
};
