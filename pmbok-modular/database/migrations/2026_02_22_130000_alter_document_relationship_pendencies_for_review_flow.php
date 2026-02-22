<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('document_relationship_pendencies', function (Blueprint $table) {
            $table->dropUnique(['relationship_id']);

            $table->foreignId('trigger_document_id')
                ->nullable()
                ->after('relationship_id')
                ->constrained('documents')
                ->cascadeOnDelete();

            $table->string('trigger_paragraph_id')->nullable()->after('trigger_document_id');

            $table->foreignId('reviewed_by')
                ->nullable()
                ->after('trigger_paragraph_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');

            $table->index(['relationship_id', 'reviewed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_relationship_pendencies', function (Blueprint $table) {
            $table->dropIndex(['relationship_id', 'reviewed_at']);

            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn('reviewed_at');

            $table->dropColumn('trigger_paragraph_id');
            $table->dropConstrainedForeignId('trigger_document_id');

            $table->unique('relationship_id');
        });
    }
};
