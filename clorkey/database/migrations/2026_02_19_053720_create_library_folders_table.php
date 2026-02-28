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
        Schema::create('library_folders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('library_book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('library_folders')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('library_folders');
    }
};
