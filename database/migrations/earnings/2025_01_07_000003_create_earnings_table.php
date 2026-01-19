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
        Schema::create('earnings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consolidated_id');
            $table->unsignedBigInteger('earning_type_id');
            $table->unsignedBigInteger('company_earning_id')->nullable();
            $table->dateTime('date');
            $table->decimal('quantity', 18, 8);
            $table->decimal('net_value', 18, 8);
            $table->decimal('gross_value', 18, 8)->nullable();
            $table->decimal('tax', 18, 8)->nullable();
            $table->string('imported_with', 20)->default('Manual');
            $table->timestamps();
            // $table->softDeletes();

            // Indexes
            $table->index('earning_type_id', 'fk_earnings_earning_type1_idx');
            $table->index('consolidated_id', 'fk_earnings_consolidated1_idx');
            $table->index('company_earning_id', 'fk_earnings_company_earnings1_idx');

            // Foreign keys
            $table->foreign('earning_type_id', 'fk_earnings_earning_type1')
                ->references('id')
                ->on('earning_type')
                ->onDelete('restrict')
                ->onUpdate('restrict');

            $table->foreign('consolidated_id', 'fk_earnings_consolidated1')
                ->references('id')
                ->on('consolidated')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('company_earning_id', 'fk_earnings_company_earnings1')
                ->references('id')
                ->on('company_earnings')
                ->onDelete('restrict')
                ->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('earnings');
    }
};
