<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->string('pending_plan_slug', 50)
                ->nullable()
                ->after('plan_slug')
                ->comment('Plano agendado para downgrade na proxima renovacao');
            $table->timestamp('pending_effective_at')
                ->nullable()
                ->after('pending_plan_slug')
                ->comment('Quando o downgrade deve entrar em vigor');
        });
    }

    public function down(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['pending_plan_slug', 'pending_effective_at']);
        });
    }
};
