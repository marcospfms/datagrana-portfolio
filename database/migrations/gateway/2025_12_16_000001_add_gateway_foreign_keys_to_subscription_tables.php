<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clean up orphaned values before adding foreign keys (prevents MySQL 1452 errors).
        if (
            Schema::hasTable('plan_period') &&
            Schema::hasColumn('plan_period', 'gateway_billing_type_id') &&
            Schema::hasTable('gateway_billing_type')
        ) {
            DB::table('plan_period')
                ->whereNotNull('gateway_billing_type_id')
                ->whereNotIn('gateway_billing_type_id', DB::table('gateway_billing_type')->select('id'))
                ->update(['gateway_billing_type_id' => null]);
        }

        if (
            Schema::hasTable('subscription') &&
            Schema::hasColumn('subscription', 'gateway_id') &&
            Schema::hasTable('gateway')
        ) {
            DB::table('subscription')
                ->whereNotNull('gateway_id')
                ->whereNotIn('gateway_id', DB::table('gateway')->select('id'))
                ->update(['gateway_id' => null]);
        }

        if (
            Schema::hasTable('subscription') &&
            Schema::hasColumn('subscription', 'gateway_billing_type_id') &&
            Schema::hasTable('gateway_billing_type')
        ) {
            DB::table('subscription')
                ->whereNotNull('gateway_billing_type_id')
                ->whereNotIn('gateway_billing_type_id', DB::table('gateway_billing_type')->select('id'))
                ->update(['gateway_billing_type_id' => null]);
        }

        Schema::table('plan_period', function (Blueprint $table) {
            $table
                ->foreign('gateway_billing_type_id')
                ->references('id')
                ->on('gateway_billing_type')
                ->nullOnDelete();
        });

        Schema::table('subscription', function (Blueprint $table) {
            $table
                ->foreign('gateway_id')
                ->references('id')
                ->on('gateway')
                ->nullOnDelete();

            $table
                ->foreign('gateway_billing_type_id')
                ->references('id')
                ->on('gateway_billing_type')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('subscription')) {
            Schema::table('subscription', function (Blueprint $table) {
                $table->dropForeign(['gateway_billing_type_id']);
                $table->dropForeign(['gateway_id']);
            });
        }

        if (Schema::hasTable('plan_period')) {
            Schema::table('plan_period', function (Blueprint $table) {
                $table->dropForeign(['gateway_billing_type_id']);
            });
        }
    }
};
