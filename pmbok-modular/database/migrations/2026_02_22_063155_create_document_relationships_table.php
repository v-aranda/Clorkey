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
        Schema::create('document_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_document_id')->constrained('documents')->onDelete('cascade');
            $table->string('source_paragraph_id');
            $table->foreignId('target_document_id')->constrained('documents')->onDelete('cascade');
            $table->string('target_paragraph_id');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Helpful to query fast
            $table->index(['source_document_id', 'source_paragraph_id']);
            $table->index(['target_document_id', 'target_paragraph_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_relationships');
    }
};
