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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_category_id');
            $table->string('name', 200);
            $table->tinyInteger('status')->default(1);
            $table->char('cnpj', 18)->nullable();
            $table->string('nickname', 200)->nullable();
            $table->text('photo')->nullable();
            $table->string('segment', 80)->nullable();
            $table->string('sector', 80)->nullable();
            $table->string('subsector', 80)->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Índices e chaves estrangeiras
            $table->index('company_category_id');
            $table->foreign('company_category_id')
                ->references('id')
                ->on('company_category')
                ->onDelete('restrict')
                ->onUpdate('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
