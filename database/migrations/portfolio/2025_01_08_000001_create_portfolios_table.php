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
        Schema::create('portfolios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name', 80);
            $table->decimal('month_value', 12, 2)->comment('Investimento mensal');
            $table->decimal('target_value', 12, 2)->comment('Objetivo atual');
            $table->timestamps();
            $table->softDeletes();

            // Index
            $table->index('user_id', 'fk_portfolio_users1_idx');

            // Foreign key
            $table->foreign('user_id', 'fk_portfolio_users1')
                ->references('id')
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portfolios');
    }
};
