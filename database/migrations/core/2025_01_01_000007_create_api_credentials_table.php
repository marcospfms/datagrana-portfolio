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
        Schema::create('api_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('email', 50);
            $table->string('key', 30);
            $table->string('url_base', 300);
            $table->boolean('status')->default(0);
            $table->integer('request_counter')->default(0);
            $table->integer('request_limit')->nullable();
            $table->enum('type_limit', ['daily', 'monthly'])->nullable();
            $table->enum('plan', ['free', 'paid'])->nullable();
            $table->string('token', 200)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_credentials');
    }
};
