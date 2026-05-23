<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_ticket_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('default_group_id')->nullable()->constrained('workflow_groups')->nullOnDelete();
            $table->foreignId('default_department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->unsignedInteger('resolve_max_duration')->default(168);
            $table->boolean('enabled')->default(false);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('workflow_ticket_template_department', function (Blueprint $table) {
            $table->foreignId('ticket_template_id')->constrained('workflow_ticket_templates')->cascadeOnDelete();
            $table->foreignId('workflow_department_id')->constrained('hr_departments')->cascadeOnDelete();
            $table->primary(['ticket_template_id', 'workflow_department_id']);
        });

        Schema::create('workflow_procedure_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('default_group_id')->nullable()->constrained('workflow_groups')->nullOnDelete();
            $table->boolean('creator_see_tasks')->default(false);
            $table->boolean('enabled')->default(false);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('flowchart_sub_positions')->nullable();
            $table->timestamps();
        });

        Schema::create('workflow_procedure_template_department', function (Blueprint $table) {
            $table->foreignId('procedure_template_id')->constrained('workflow_procedure_templates')->cascadeOnDelete();
            $table->foreignId('workflow_department_id')->constrained('hr_departments')->cascadeOnDelete();
            $table->primary(['procedure_template_id', 'workflow_department_id']);
        });

        Schema::create('workflow_procedure_steps', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('procedure_template_id')->constrained('workflow_procedure_templates')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('default_department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->boolean('is_approve_only')->default(false);
            $table->boolean('has_procedures')->default(false);
            $table->boolean('procedures_required')->default(false);
            $table->boolean('ignore_state')->default(false);
            $table->boolean('has_path_choice')->default(false);
            $table->string('path_choice_question')->nullable();
            $table->boolean('path_choice_required')->default(false);
            $table->integer('flowchart_x')->default(0);
            $table->integer('flowchart_y')->default(0);
            $table->boolean('flowchart_position_saved')->default(false);
            $table->boolean('enabled')->default(true);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('workflow_procedure_step_next', function (Blueprint $table) {
            $table->foreignId('step_id')->constrained('workflow_procedure_steps')->cascadeOnDelete();
            $table->foreignId('next_step_id')->constrained('workflow_procedure_steps')->cascadeOnDelete();
            $table->primary(['step_id', 'next_step_id']);
        });

        Schema::create('workflow_procedure_step_paths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('step_id')->constrained('workflow_procedure_steps')->cascadeOnDelete();
            $table->foreignId('target_step_id')->constrained('workflow_procedure_steps')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('workflow_procedure_step_sub_proc', function (Blueprint $table) {
            $table->foreignId('step_id')->constrained('workflow_procedure_steps')->cascadeOnDelete();
            $table->foreignId('procedure_template_id')->constrained('workflow_procedure_templates')->cascadeOnDelete();
            $table->primary(['step_id', 'procedure_template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_procedure_step_sub_proc');
        Schema::dropIfExists('workflow_procedure_step_paths');
        Schema::dropIfExists('workflow_procedure_step_next');
        Schema::dropIfExists('workflow_procedure_steps');
        Schema::dropIfExists('workflow_procedure_template_department');
        Schema::dropIfExists('workflow_procedure_templates');
        Schema::dropIfExists('workflow_ticket_template_department');
        Schema::dropIfExists('workflow_ticket_templates');
    }
};
