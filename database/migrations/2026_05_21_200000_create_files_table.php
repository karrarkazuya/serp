<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('disk', 20)->default('local');
            $table->string('path');
            $table->string('thumbnail_path')->nullable();
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->string('extension', 20)->default('');
            $table->unsignedBigInteger('size');
            // null = context-only gate (e.g. chat room membership); otherwise user must hasPermission($key)
            $table->string('permission_key', 100)->nullable();
            $table->nullableMorphs('context'); // context_type + context_id for ownership checks (Ticket, ChatRoom)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
