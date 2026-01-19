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
        Schema::create('compositions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('portfolio_id');
            $table->unsignedBigInteger('treasure_id')->nullable();
            $table->unsignedBigInteger('company_ticker_id')->nullable();
            $table->double('percentage', 10, 2);
            $table->timestamps();

            // Indexes
            $table->index('portfolio_id', 'fk_composition_portfolio1_idx');
            $table->index('treasure_id', 'fk_composition_treasures1_idx');
            $table->index('company_ticker_id', 'fk_compositions_company_tickers1_idx');

            // Foreign keys
            $table->foreign('portfolio_id', 'fk_composition_portfolio1')
                ->references('id')
                ->on('portfolios')
                ->onDelete('restrict')
                ->onUpdate('restrict');

            $table->foreign('treasure_id', 'fk_composition_treasures1')
                ->references('id')
                ->on('treasures')
                ->onDelete('restrict')
                ->onUpdate('restrict');

            $table->foreign('company_ticker_id', 'fk_compositions_company_tickers1')
                ->references('id')
                ->on('company_tickers')
                ->onDelete('restrict')
                ->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compositions');
    }
};
