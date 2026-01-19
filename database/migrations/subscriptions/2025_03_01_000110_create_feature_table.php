<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('feature_group')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('reference')->unique();
            $table->string('scope', 20);
            $table->string('unit')->nullable();
            $table->boolean('status')->default(true);
            $table->decimal('default_value', 18, 4)->nullable();
            $table->boolean('can_customize')->default(false);
            $table->json('config')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature');
    }
};
