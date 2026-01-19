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
        Schema::create('company_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_ticker_id')->nullable()->constrained('company_tickers')->onDelete('restrict')->onUpdate('restrict');
            $table->string('name', 80);
            $table->string('short_name', 50);
            $table->string('key', 25);
            $table->integer('year');
            $table->decimal('value', 22, 8);
            $table->boolean('percentage')->default(false);
            $table->timestamps();

            // Índices únicos
            $table->unique(['key', 'year', 'company_ticker_id'], 'unique_ticker');
            // Índices para performance
            $table->index('company_ticker_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_indicators');
    }
};
