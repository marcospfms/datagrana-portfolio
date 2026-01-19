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
        Schema::create('consolidated', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('treasure_id')->nullable();
            $table->unsignedBigInteger('company_ticker_id')->nullable();
            $table->decimal('average_purchase_price', 18, 8);
            $table->decimal('quantity_current', 18, 8);
            $table->decimal('total_purchased', 18, 8);
            $table->boolean('closed')->default(false);
            $table->decimal('average_selling_price', 18, 8)->nullable();
            $table->decimal('total_sold', 18, 8)->nullable();
            $table->decimal('quantity_purchased', 18, 8)->nullable();
            $table->decimal('quantity_sold', 18, 8)->nullable();
            $table->timestamps();

            // Indexes
            $table->index('treasure_id', 'fk_wallet_treasures1_idx');
            $table->index('account_id', 'fk_consolidated_accounts1_idx');
            $table->index('company_ticker_id', 'fk_consolidated_company_tickers1_idx');

            // Foreign keys
            $table->foreign('treasure_id', 'fk_wallet_treasures1')
                ->references('id')
                ->on('treasures')
                ->onDelete('restrict')
                ->onUpdate('restrict');

            $table->foreign('account_id', 'fk_consolidated_accounts1')
                ->references('id')
                ->on('accounts')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('company_ticker_id', 'fk_consolidated_company_tickers1')
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
        Schema::dropIfExists('consolidated');
    }
};
