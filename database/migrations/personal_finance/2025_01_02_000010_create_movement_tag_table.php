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
        Schema::create('movement_tag', function (Blueprint $table) {
            $table->foreignId('movement_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->primary(['movement_id', 'tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movement_tag');
    }
};
