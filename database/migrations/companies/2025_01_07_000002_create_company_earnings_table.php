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
        Schema::create('company_earnings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_ticker_id');
            $table->unsignedBigInteger('earning_type_id');
            $table->string('origin', 50)->default('manual');
            $table->tinyInteger('status')->default(1);
            $table->decimal('value', 18, 8);
            $table->date('approved_date');
            $table->date('payment_date')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('company_ticker_id', 'fk_company_earnings_company_tickers1_idx');
            $table->index('earning_type_id', 'fk_company_earnings_earning_type1_idx');

            // Foreign key constraints
            $table->foreign('company_ticker_id', 'fk_company_earnings_company_tickers1')
                ->references('id')
                ->on('company_tickers')
                ->onDelete('no action')
                ->onUpdate('no action');

            $table->foreign('earning_type_id', 'fk_company_earnings_earning_type1')
                ->references('id')
                ->on('earning_type')
                ->onDelete('no action')
                ->onUpdate('no action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_earnings');
    }
};
