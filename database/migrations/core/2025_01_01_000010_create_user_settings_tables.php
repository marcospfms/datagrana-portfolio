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
        Schema::create('user_setting_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('module', 100);
            $table->string('key', 150);
            $table->string('label', 150);
            $table->text('description')->nullable();
            $table->string('data_type', 50);
            $table->json('default_value')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->unique(['module', 'key']);
        });

        Schema::create('user_setting_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('definition_id')
                ->constrained('user_setting_definitions')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('scope_type', 100);
            $table->string('scope_reference', 150)->nullable();
            $table->timestamps();

            $table->index(['definition_id', 'scope_type']);
        });

        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('definition_id')
                ->constrained('user_setting_definitions')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->json('value')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'definition_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_settings');
        Schema::dropIfExists('user_setting_scopes');
        Schema::dropIfExists('user_setting_definitions');
    }
};

