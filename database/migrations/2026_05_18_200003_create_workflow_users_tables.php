<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workflow_users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('default_department_id')->nullable()->constrained('workflow_departments')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Groups a workflow user can see (visibility groups)
        Schema::create('workflow_user_group', function (Blueprint $table) {
            $table->foreignId('workflow_user_id')->constrained('workflow_users')->cascadeOnDelete();
            $table->foreignId('workflow_group_id')->constrained('workflow_groups')->cascadeOnDelete();
            $table->primary(['workflow_user_id', 'workflow_group_id']);
        });

        // Departments a workflow user is allowed to assign work to
        Schema::create('workflow_user_dept_assign', function (Blueprint $table) {
            $table->foreignId('workflow_user_id')->constrained('workflow_users')->cascadeOnDelete();
            $table->foreignId('workflow_department_id')->constrained('workflow_departments')->cascadeOnDelete();
            $table->primary(['workflow_user_id', 'workflow_department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_user_dept_assign');
        Schema::dropIfExists('workflow_user_group');
        Schema::dropIfExists('workflow_users');
    }
};
