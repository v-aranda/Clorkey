<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_tasks', function (Blueprint $table) {
            $table->uuid('recurrence_group_id')->nullable()->index()->after('participants');
            $table->json('recurrence_config')->nullable()->after('recurrence_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('agenda_tasks', function (Blueprint $table) {
            $table->dropColumn(['recurrence_group_id', 'recurrence_config']);
        });
    }
};
