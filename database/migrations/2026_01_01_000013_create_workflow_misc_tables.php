<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_ticket_procedure_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('ticket_id')->constrained('workflow_tickets')->cascadeOnDelete();
            $table->foreignId('procedure_template_id')->constrained('workflow_procedure_templates')->restrictOnDelete();
            $table->unsignedBigInteger('procedure_id')->nullable();
            $table->string('name');
            $table->string('state')->default('pending');
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('workflow_shared_links', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique()->nullable();
            $table->morphs('shareable');
            $table->string('token', 64)->unique();
            $table->text('message')->nullable();
            $table->boolean('enabled')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('workflow_allowed_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('record_id');
            $table->string('record_type', 20);
            $table->timestamps();

            $table->unique(['user_id', 'record_id', 'record_type']);
            $table->index(['record_id', 'record_type', 'user_id'], 'wau_record_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_allowed_users');
        Schema::dropIfExists('workflow_shared_links');
        Schema::dropIfExists('workflow_ticket_procedure_lines');
    }
};
