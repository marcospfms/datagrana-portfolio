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
        Schema::create('company_tickers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('code', 12);
            $table->string('trade_code', 12)->default('BVMF');
            $table->tinyInteger('status')->default(1);
            $table->tinyInteger('can_update')->nullable()->default(1);
            $table->decimal('last_price', 18, 8)->nullable();
            $table->timestamp('last_price_updated')->nullable();
            $table->timestamp('last_earnings_updated')->nullable();
            $table->timestamps();

            // Índices e chaves estrangeiras
            $table->index('company_id', 'fk_company_tickers_companies1_idx');
            $table->foreign('company_id', 'fk_company_tickers_companies1')
                ->references('id')
                ->on('companies')
                ->onDelete('no action')
                ->onUpdate('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_tickers');
    }
};
