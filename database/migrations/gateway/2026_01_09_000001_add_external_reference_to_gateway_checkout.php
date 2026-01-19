<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gateway_checkout', function (Blueprint $table) {
            if (!Schema::hasColumn('gateway_checkout', 'external_reference')) {
                $table->string('external_reference')
                    ->nullable()
                    ->after('status')
                    ->index()
                    ->comment('Referência externa no formato checkout-{id}');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gateway_checkout', function (Blueprint $table) {
            if (Schema::hasColumn('gateway_checkout', 'external_reference')) {
                $table->dropIndex(['external_reference']);
                $table->dropColumn('external_reference');
            }
        });
    }
};
