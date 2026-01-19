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
        Schema::create('closing_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_closing_id')->constrained('credit_closings')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('closing_movement_id')->constrained('movements')->cascadeOnDelete()->cascadeOnUpdate()->comment('Movimentação lançada como pagamento parcial ou total da fatura (tipo income)');
            $table->foreignId('paid_movement_id')->nullable()->constrained('movements')->nullOnDelete()->cascadeOnUpdate()->comment('Movimentação de crédito que foi paga ou parcialmente paga pela movimentação (tipo expense)');
            $table->foreignId('paying_movement_id')->nullable()->constrained('movements')->nullOnDelete()->cascadeOnUpdate()->comment('Movimentação de crédito ou débito do tipo expense opcional, indicando que a entrada na fatura teve uma contrapartida de despesa');
            $table->timestamps();

            $table->index('credit_closing_id', 'idx_closing_payments_credit_closing_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('closing_payments');
    }
};
