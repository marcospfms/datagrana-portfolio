<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Índice composto em gateway_charge para busca por external_reference + gateway_id
        Schema::table('gateway_charge', function (Blueprint $table) {
            if (!Schema::hasIndex('gateway_charge', 'idx_charge_gateway_extref')) {
                $table->index(['gateway_id', 'external_reference'], 'idx_charge_gateway_extref');
            }
        });

        // Índice em gateway_subscription.external_reference
        Schema::table('gateway_subscription', function (Blueprint $table) {
            if (!Schema::hasIndex('gateway_subscription', 'idx_subscription_extref')) {
                $table->index('external_reference', 'idx_subscription_extref');
            }
        });

        // Índice em gateway_payment_link.external_reference
        Schema::table('gateway_payment_link', function (Blueprint $table) {
            if (!Schema::hasIndex('gateway_payment_link', 'idx_payment_link_extref')) {
                $table->index('external_reference', 'idx_payment_link_extref');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gateway_charge', function (Blueprint $table) {
            if (Schema::hasIndex('gateway_charge', 'idx_charge_gateway_extref')) {
                $table->dropIndex('idx_charge_gateway_extref');
            }
        });

        Schema::table('gateway_subscription', function (Blueprint $table) {
            if (Schema::hasIndex('gateway_subscription', 'idx_subscription_extref')) {
                $table->dropIndex('idx_subscription_extref');
            }
        });

        Schema::table('gateway_payment_link', function (Blueprint $table) {
            if (Schema::hasIndex('gateway_payment_link', 'idx_payment_link_extref')) {
                $table->dropIndex('idx_payment_link_extref');
            }
        });
    }
};
