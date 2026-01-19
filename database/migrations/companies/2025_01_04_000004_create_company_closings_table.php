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
        Schema::create('company_closings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_ticker_id');
            $table->date('date');
            $table->decimal('open', 18, 8);
            $table->decimal('high', 18, 8);
            $table->decimal('low', 18, 8);
            $table->decimal('price', 18, 8);
            $table->decimal('volume', 18, 8);
            $table->decimal('previous_close', 18, 8)->nullable();
            $table->tinyInteger('splitted')->nullable()->default(0)->comment('sinaliza que o historico de preço foi ajustado ');
            $table->timestamps();

            // Unique index for company_ticker_id and date
            $table->unique(['company_ticker_id', 'date'], 'unique_ticker_date');

            // Foreign key constraint
            $table->foreign('company_ticker_id', 'fk_company_closings_company_tickers1')
                ->references('id')
                ->on('company_tickers')
                ->onDelete('no action')
                ->onUpdate('no action');

            // Index for company_ticker_id
            $table->index('company_ticker_id', 'fk_company_closings_company_tickers1_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_closings');
    }
};
