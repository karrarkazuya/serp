<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workflow_ticket_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('default_group_id')->nullable()->constrained('workflow_groups')->nullOnDelete();
            $table->foreignId('default_department_id')->nullable()->constrained('workflow_departments')->nullOnDelete();
            $table->unsignedInteger('resolve_max_duration')->default(168);
            $table->boolean('enabled')->default(false);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Departments allowed to create tickets from this template
        Schema::create('workflow_ticket_template_department', function (Blueprint $table) {
            $table->foreignId('ticket_template_id')->constrained('workflow_ticket_templates')->cascadeOnDelete();
            $table->foreignId('workflow_department_id')->constrained('workflow_departments')->cascadeOnDelete();
            $table->primary(['ticket_template_id', 'workflow_department_id']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_ticket_template_department');
        Schema::dropIfExists('workflow_ticket_templates');
    }
};
