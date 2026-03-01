<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('agenda_tasks', function (Blueprint $table) {
            $table->bigInteger('sort_order')->default(0)->after('status');
            $table->index(['user_id', 'sort_order']);
        });

        // Initialise sort_order for existing rows using created_at epoch millis
        DB::statement("
            UPDATE agenda_tasks
            SET sort_order = EXTRACT(EPOCH FROM created_at) * 1000
            WHERE sort_order = 0
        ");
    }

    public function down(): void
    {
        Schema::table('agenda_tasks', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
