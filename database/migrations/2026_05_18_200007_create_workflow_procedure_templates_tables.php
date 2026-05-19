<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workflow_procedure_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('default_group_id')->nullable()->constrained('workflow_groups')->nullOnDelete();
            $table->unsignedInteger('resolve_max_duration')->default(168);
            $table->boolean('creator_see_tasks')->default(false);
            $table->boolean('enabled')->default(false);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('workflow_procedure_template_department', function (Blueprint $table) {
            $table->foreignId('procedure_template_id')->constrained('workflow_procedure_templates')->cascadeOnDelete();
            $table->foreignId('workflow_department_id')->constrained('workflow_departments')->cascadeOnDelete();
            $table->primary(['procedure_template_id', 'workflow_department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_procedure_template_department');
        Schema::dropIfExists('workflow_procedure_templates');
    }
};
