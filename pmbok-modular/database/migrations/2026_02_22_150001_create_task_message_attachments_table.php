<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_message_id')->constrained()->cascadeOnDelete();
            $table->string('path');           // storage path (relative to public disk)
            $table->string('original_name');  // original filename
            $table->enum('type', ['image', 'video', 'doc'])->default('doc');
            $table->unsignedBigInteger('size')->nullable(); // bytes
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_message_attachments');
    }
};
