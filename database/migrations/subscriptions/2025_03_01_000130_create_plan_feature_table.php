<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_feature', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plan')->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained('feature')->cascadeOnDelete();
            $table->boolean('status')->default(true);
            $table->decimal('value_decimal', 18, 4)->nullable();
            $table->json('value_json')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['plan_id', 'feature_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_feature');
    }
};
