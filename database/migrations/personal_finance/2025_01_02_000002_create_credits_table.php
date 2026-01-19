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
        Schema::create('credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->decimal('limit', 18, 8);
            $table->decimal('balance', 18, 8)->default(0);
            $table->integer('due_date');
            $table->integer('days_to_closing');
            $table->char('final', 4);
            $table->boolean('default')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credits');
    }
};
