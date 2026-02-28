<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_tasks', function (Blueprint $table) {
            $table->dropColumn('deadline');
            $table->unsignedInteger('resolution_time')->nullable()->after('end_time');
            $table->string('resolution_unit', 20)->nullable()->after('resolution_time');
        });
    }

    public function down(): void
    {
        Schema::table('agenda_tasks', function (Blueprint $table) {
            $table->dropColumn(['resolution_time', 'resolution_unit']);
            $table->dateTime('deadline')->nullable()->after('end_time');
        });
    }
};
