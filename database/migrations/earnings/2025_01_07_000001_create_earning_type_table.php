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
        Schema::create('earning_type', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('short_name', 60)->nullable();
            $table->string('label', 60)->nullable();
            $table->string('key', 10)->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('hex_color', 50)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('earning_type');
    }
};
