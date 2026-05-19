<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workflow_allowed_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('record_id');
            $table->string('record_type', 20); // ticket, task, procedure
            $table->timestamps();

            $table->unique(['user_id', 'record_id', 'record_type']);
            // Covering index for "can user X see ticket Y?" EXISTS checks
            $table->index(['record_id', 'record_type', 'user_id'], 'wau_record_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_allowed_users');
    }
};
