<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workflow_tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('template_id')->nullable()->constrained('workflow_ticket_templates')->restrictOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('state')->default('pending'); // draft, pending, completed, rejected, skipped, closed
            $table->string('priority')->default('1'); // 1, 2, 3
            $table->foreignId('assigned_to_department_id')->nullable()->constrained('workflow_departments')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('resolve_max_duration')->default(168);
            $table->dateTime('resolve_deadline')->nullable();
            $table->integer('resolve_duration')->default(0);
            $table->integer('resolve_deadline_passed')->default(0);
            $table->boolean('share_enabled')->default(false);
            $table->string('share_token')->nullable()->unique();
            // procedure-ticket columns
            $table->unsignedInteger('task_sequence')->default(0);
            $table->boolean('is_approve_only')->default(false);
            $table->boolean('has_path_choice')->default(false);
            $table->string('path_choice_question')->nullable();
            $table->boolean('has_procedures')->default(false);
            $table->boolean('ignore_state')->default(false);
            $table->text('return_reason')->nullable();
            $table->dateTime('unlock_at')->nullable();
            $table->boolean('finished_creation')->default(false);
            // optional links
            $table->foreignId('optional_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('optional_ticket_id')->nullable()->constrained('workflow_tickets')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('workflow_ticket_durations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('workflow_tickets')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('workflow_departments')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('duration', 10, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_ticket_durations');
        Schema::dropIfExists('workflow_tickets');
    }
};
