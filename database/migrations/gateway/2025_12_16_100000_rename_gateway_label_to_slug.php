<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Renomear label para slug na tabela gateway
        Schema::table('gateway', function (Blueprint $table) {
            $table->renameColumn('label', 'slug');
        });

        Schema::table('gateway', function (Blueprint $table) {
            $table->unique('slug');
        });

        // Adicionar canceled_at na tabela gateway_charge
        Schema::table('gateway_charge', function (Blueprint $table) {
            $table->timestamp('canceled_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('gateway_charge', function (Blueprint $table) {
            $table->dropColumn('canceled_at');
        });

        Schema::table('gateway', function (Blueprint $table) {
            $table->dropUnique(['slug']);
        });

        Schema::table('gateway', function (Blueprint $table) {
            $table->renameColumn('slug', 'label');
        });
    }
};
