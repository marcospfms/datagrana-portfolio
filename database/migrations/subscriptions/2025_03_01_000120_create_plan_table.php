<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('segment')->nullable();
            $table->string('tagline')->nullable();
            $table->string('highlight_badge')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_trial')->default(false);
            $table->boolean('is_highlighted')->default(false);
            $table->boolean('status')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan');
    }
};
