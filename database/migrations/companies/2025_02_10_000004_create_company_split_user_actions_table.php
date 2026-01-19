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
        Schema::create('company_split_user_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_split_id');
            $table->unsignedBigInteger('user_id');
            $table->string('status', 20)->default('pending');
            $table->json('applied_transaction_ids')->nullable();
            $table->json('applied_earning_ids')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->foreign('company_split_id')
                ->references('id')
                ->on('company_splits')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->unique(['company_split_id', 'user_id'], 'uniq_company_split_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_split_user_actions');
    }
};
