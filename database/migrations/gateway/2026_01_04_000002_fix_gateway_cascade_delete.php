<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Corrige cascade delete agressivo que poderia causar perda de dados históricos.
     * Altera de cascadeOnDelete para nullOnDelete em tabelas relacionadas ao gateway.
     */
    public function up(): void
    {
        // Tabelas que têm FK para gateway_id
        $tablesWithFk = [
            'gateway_charge',
            'gateway_subscription',
            'gateway_card_token',
            'gateway_payment_link',
            'gateway_checkout',
            'gateway_webhook_log',
            'gateway_billing_type',
        ];

        // Tabelas que não têm FK mas precisam ter gateway_id nullable
        $tablesWithoutFk = [
            'gateway_customer',
        ];

        // Passo 1: Dropar FKs existentes (apenas nas tabelas que têm)
        foreach ($tablesWithFk as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['gateway_id']);
            });
        }

        // Passo 2: Alterar colunas para nullable (todas as tabelas)
        foreach (array_merge($tablesWithFk, $tablesWithoutFk) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('gateway_id')->nullable()->change();
            });
        }

        // Passo 3: Recriar FKs com nullOnDelete (apenas nas tabelas que tinham)
        foreach ($tablesWithFk as $tableName) {
            $constraintName = $tableName . '_gateway_id_foreign';
            if (! $this->foreignKeyExists($tableName, $constraintName)) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreign('gateway_id')
                        ->references('id')
                        ->on('gateway')
                        ->nullOnDelete();
                });
            }
        }

        // Passo 4: Adicionar FK para gateway_customer (que nao tinha)
        $gatewayCustomerFk = 'gateway_customer_gateway_id_foreign';
        if (! $this->foreignKeyExists('gateway_customer', $gatewayCustomerFk)) {
            Schema::table('gateway_customer', function (Blueprint $table) {
                $table->foreign('gateway_id')
                    ->references('id')
                    ->on('gateway')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'gateway_customer',
            'gateway_charge',
            'gateway_subscription',
            'gateway_card_token',
            'gateway_payment_link',
            'gateway_checkout',
            'gateway_webhook_log',
            'gateway_billing_type',
        ];

        // Passo 1: Dropar FKs
        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['gateway_id']);
            });
        }

        // Passo 2: Tornar colunas NOT NULL
        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('gateway_id')->nullable(false)->change();
            });
        }

        // Passo 3: Recriar FKs com cascadeOnDelete (exceto gateway_customer que não tinha)
        $tablesWithFk = [
            'gateway_charge',
            'gateway_subscription',
            'gateway_card_token',
            'gateway_payment_link',
            'gateway_checkout',
            'gateway_webhook_log',
            'gateway_billing_type',
        ];

        foreach ($tablesWithFk as $tableName) {
            $constraintName = $tableName . '_gateway_id_foreign';
            if (! $this->foreignKeyExists($tableName, $constraintName)) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreign('gateway_id')
                        ->references('id')
                        ->on('gateway')
                        ->cascadeOnDelete();
                });
            }
        }
    }

    private function foreignKeyExists(string $tableName, string $constraintName): bool
    {
        $result = DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ? LIMIT 1',
            [DB::getDatabaseName(), $tableName, $constraintName, 'FOREIGN KEY']
        );

        return $result !== null;
    }
};
