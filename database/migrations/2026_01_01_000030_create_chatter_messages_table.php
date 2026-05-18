<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatter_messages', function (Blueprint $table) {
            $table->id();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('message_type', ['log', 'comment', 'system'])->default('log');
            $table->text('body');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['model_type', 'model_id']);
            $table->index('message_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatter_messages');
    }
};
