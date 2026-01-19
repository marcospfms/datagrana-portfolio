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
        Schema::create('user_net_balance', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('consolidated_id');
            $table->date('date');
            $table->decimal('net_balance', 18, 8);
            $table->timestamps();

            // Indexes
            $table->index('user_id', 'fk_users_has_treasures_users1_idx');
            $table->index('consolidated_id', 'fk_user_net_balance_consolidated1_idx');

            // Unique constraint
            $table->unique(['user_id', 'date'], 'unique_date');

            // Foreign keys
            $table->foreign('user_id', 'fk_users_has_treasures_users1')
                ->references('id')
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('restrict');

            $table->foreign('consolidated_id', 'fk_user_net_balance_consolidated1')
                ->references('id')
                ->on('consolidated')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_net_balance');
    }
};
