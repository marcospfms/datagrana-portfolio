<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('gateway', function (Blueprint $table) {
            $table->string('provider_key')->nullable()->after('slug');
            $table->index('provider_key');
        });

    }

    public function down(): void
    {
        Schema::table('gateway', function (Blueprint $table) {
            $table->dropIndex(['provider_key']);
            $table->dropColumn('provider_key');
        });
    }
};

