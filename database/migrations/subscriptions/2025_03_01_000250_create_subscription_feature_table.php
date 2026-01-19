<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_feature', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscription')->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained('feature')->cascadeOnDelete();
            $table->decimal('value_decimal', 18, 4)->nullable();
            $table->json('value_json')->nullable();
            $table->boolean('status')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['subscription_id', 'feature_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_feature');
    }
};
