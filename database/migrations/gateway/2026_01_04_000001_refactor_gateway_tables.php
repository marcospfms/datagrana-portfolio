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
        // 1. Atualizar tabela gateway - remover provider_class e tornar provider_key obrigatório
        if (Schema::hasTable('gateway') && Schema::hasColumn('gateway', 'provider_class')) {
            Schema::table('gateway', function (Blueprint $table) {
                $table->dropColumn('provider_class');
            });
        }

        Schema::table('gateway', function (Blueprint $table) {
            $table->string('provider_key')->nullable(false)->change();
        });

        // 2. Tornar user_id e name nullable em gateway_customer
        Schema::table('gateway_customer', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('name')->nullable()->change();
        });

        // 3. Tornar user_id nullable em gateway_charge
        Schema::table('gateway_charge', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });

        // 4. Tornar user_id nullable em gateway_subscription
        Schema::table('gateway_subscription', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });

        // 5. Tornar user_id nullable em gateway_card_token
        Schema::table('gateway_card_token', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverter gateway_card_token
        Schema::table('gateway_card_token', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });

        // Reverter gateway_subscription
        Schema::table('gateway_subscription', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });

        // Reverter gateway_charge
        Schema::table('gateway_charge', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });

        // Reverter gateway_customer
        Schema::table('gateway_customer', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
            $table->string('name')->nullable(false)->change();
        });

        // Reverter gateway - tornar provider_key nullable e adicionar provider_class
        Schema::table('gateway', function (Blueprint $table) {
            $table->string('provider_key')->nullable()->change();
        });

        Schema::table('gateway', function (Blueprint $table) {
            $table->string('provider_class')->nullable()->after('provider_key');
        });
    }
};
