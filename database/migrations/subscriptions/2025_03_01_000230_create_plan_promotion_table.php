<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_promotion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_period_id')->constrained('plan_period')->cascadeOnDelete();
            $table->string('code')->nullable();
            $table->string('type', 20);
            $table->decimal('value', 18, 4)->default(0);
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redemptions_count')->default(0);
            $table->boolean('applies_to_new_only')->default(false);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('status')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_promotion');
    }
};
