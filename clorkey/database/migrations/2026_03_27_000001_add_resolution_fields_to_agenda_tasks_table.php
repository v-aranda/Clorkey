<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_tasks', function (Blueprint $table) {
            $table->unsignedInteger('resolution_time')->nullable()->after('deadline');
            $table->string('resolution_unit', 20)->nullable()->after('resolution_time');
        });
    }

    public function down(): void
    {
        Schema::table('agenda_tasks', function (Blueprint $table) {
            $table->dropColumn(['resolution_time', 'resolution_unit']);
        });
    }
};
