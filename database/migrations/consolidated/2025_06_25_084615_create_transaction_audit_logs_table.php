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
        Schema::create('transaction_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_type', 20);
            $table->unsignedBigInteger('transaction_id');
            $table->enum('action', ['created', 'updated', 'deleted']);
            $table->json('payload_old')->nullable();
            $table->json('payload_new')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index(['transaction_type', 'transaction_id'], 'idx_txn_audit_txn');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_audit_logs');
    }
};
