<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_procedures', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('procedure_template_id')->constrained('workflow_procedure_templates')->restrictOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('state')->default('pending');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('resolve_max_duration')->default(168);
            $table->dateTime('resolve_deadline')->nullable();
            $table->integer('resolve_duration')->default(0);
            $table->integer('resolve_deadline_passed')->default(0);
            $table->foreignId('optional_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->unsignedBigInteger('optional_ticket_id')->nullable();
            $table->unsignedBigInteger('optional_procedure_id')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('workflow_procedure_viewers', function (Blueprint $table) {
            $table->foreignId('procedure_id')->constrained('workflow_procedures')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['procedure_id', 'user_id']);
        });

        // workflow_tickets.path_chosen_id FK added in migration 000012 (circular: tickets → paths → tickets)
        Schema::create('workflow_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_room_id')->nullable()->constrained('chat_rooms')->nullOnDelete();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('template_id')->nullable()->constrained('workflow_ticket_templates')->restrictOnDelete();
            $table->foreignId('procedure_id')->nullable()->constrained('workflow_procedures')->nullOnDelete();
            $table->foreignId('procedure_step_id')->nullable()->constrained('workflow_procedure_steps')->nullOnDelete();
            $table->unsignedBigInteger('previous_ticket_id')->nullable();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('state')->default('pending');
            $table->string('priority')->default('1');
            $table->foreignId('assigned_to_department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('resolve_max_duration')->default(168);
            $table->dateTime('resolve_deadline')->nullable();
            $table->integer('resolve_duration')->default(0);
            $table->integer('resolve_deadline_passed')->default(0);
            $table->boolean('share_enabled')->default(false);
            $table->string('share_token')->nullable()->unique();
            $table->boolean('is_approve_only')->default(false);
            $table->boolean('has_path_choice')->default(false);
            $table->string('path_choice_question')->nullable();
            $table->boolean('path_choice_required')->default(false);
            $table->boolean('has_procedures')->default(false);
            $table->boolean('procedures_required')->default(false);
            $table->boolean('ignore_state')->default(false);
            $table->text('return_reason')->nullable();
            $table->dateTime('unlock_at')->nullable();
            $table->boolean('finished_creation')->default(false);
            $table->foreignId('optional_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('optional_ticket_id')->nullable()->constrained('workflow_tickets')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('workflow_tickets', function (Blueprint $table) {
            $table->foreign('previous_ticket_id')->references('id')->on('workflow_tickets')->nullOnDelete();
        });

        Schema::create('workflow_ticket_durations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('workflow_tickets')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('duration', 10, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('workflow_ticket_next', function (Blueprint $table) {
            $table->foreignId('ticket_id')->constrained('workflow_tickets')->cascadeOnDelete();
            $table->foreignId('next_ticket_id')->constrained('workflow_tickets')->cascadeOnDelete();
            $table->primary(['ticket_id', 'next_ticket_id']);
        });

        Schema::create('workflow_ticket_paths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('workflow_tickets')->cascadeOnDelete();
            $table->foreignId('target_ticket_id')->constrained('workflow_tickets')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_ticket_paths');
        Schema::dropIfExists('workflow_ticket_next');
        Schema::dropIfExists('workflow_ticket_durations');
        Schema::dropIfExists('workflow_tickets');
        Schema::dropIfExists('workflow_procedure_viewers');
        Schema::dropIfExists('workflow_procedures');
    }
};
