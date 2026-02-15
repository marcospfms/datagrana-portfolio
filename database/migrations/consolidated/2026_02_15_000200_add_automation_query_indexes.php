<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->indexExists('consolidated', 'idx_consolidated_account_ticker')) {
            Schema::table('consolidated', function (Blueprint $table) {
                $table->index(['account_id', 'company_ticker_id'], 'idx_consolidated_account_ticker');
            });
        }

        if (! $this->indexExists('company_transactions', 'idx_company_transactions_consolidated_date_op')) {
            Schema::table('company_transactions', function (Blueprint $table) {
                $table->index(
                    ['consolidated_id', 'date', 'operation'],
                    'idx_company_transactions_consolidated_date_op'
                );
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('consolidated', 'idx_consolidated_account_ticker')) {
            Schema::table('consolidated', function (Blueprint $table) {
                $table->dropIndex('idx_consolidated_account_ticker');
            });
        }

        if ($this->indexExists('company_transactions', 'idx_company_transactions_consolidated_date_op')) {
            Schema::table('company_transactions', function (Blueprint $table) {
                $table->dropIndex('idx_company_transactions_consolidated_date_op');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $result = DB::selectOne(
                'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
                [$table, $indexName]
            );

            return $result !== null;
        }

        return false;
    }
};
