<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_checkout', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gateway_id')->constrained('gateway')->cascadeOnDelete();
            $table->string('gateway_checkout_id')->index();
            $table->string('title');
            $table->text('url');
            $table->string('status')->default('active');
            $table->decimal('amount', 15, 2)->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['gateway_id', 'gateway_checkout_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_checkout');
    }
};
