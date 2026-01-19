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
        Schema::create('movement_category', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name', 150);
            $table->enum('type', ['income', 'expense']);
            $table->string('icon', 80)->nullable();
            $table->string('color_hex', 50)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movement_category');
    }
};
