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
        Schema::create('company_splits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->tinyInteger('status')->default(1);
            $table->date('approved_date');
            $table->date('split_date');
            $table->integer('before_value');
            $table->integer('after_value');
            $table->tinyInteger('history_applied')->default(0)->comment('true = aplicou desdobramento nos fechamentos passados (company_closings)');
            $table->string('origin', 50)->nullable()->comment('- chave de extração (alguma rotina ou API)');
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('company_id', 'fk_company_split_companies1')
                ->references('id')
                ->on('companies')
                ->onDelete('no action')
                ->onUpdate('no action');

            // Index for company_id
            $table->index('company_id', 'fk_company_split_companies1_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_splits');
    }
};
