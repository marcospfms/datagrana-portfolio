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
        Schema::table('gateway_charge', function (Blueprint $table) {
            $table->foreignId('gateway_checkout_id')
                ->nullable()
                ->after('gateway_id')
                ->constrained('gateway_checkout')
                ->nullOnDelete()
                ->comment('ID do checkout que gerou esta cobrança (se aplicável)');

            $table->foreignId('gateway_payment_link_id')
                ->nullable()
                ->after('gateway_checkout_id')
                ->constrained('gateway_payment_link')
                ->nullOnDelete()
                ->comment('ID do link de pagamento que gerou esta cobrança (se aplicável)');

            $table->index('gateway_checkout_id', 'idx_charge_checkout');
            $table->index('gateway_payment_link_id', 'idx_charge_payment_link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gateway_charge', function (Blueprint $table) {
            $table->dropIndex('idx_charge_checkout');
            $table->dropIndex('idx_charge_payment_link');

            $table->dropForeign(['gateway_checkout_id']);
            $table->dropForeign(['gateway_payment_link_id']);

            $table->dropColumn(['gateway_checkout_id', 'gateway_payment_link_id']);
        });
    }
};
