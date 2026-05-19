<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workflow_managers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('workflow_user_id')->constrained('workflow_users')->cascadeOnDelete();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('workflow_manager_department', function (Blueprint $table) {
            $table->foreignId('workflow_manager_id')->constrained('workflow_managers')->cascadeOnDelete();
            $table->foreignId('workflow_department_id')->constrained('workflow_departments')->cascadeOnDelete();
            $table->primary(['workflow_manager_id', 'workflow_department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_manager_department');
        Schema::dropIfExists('workflow_managers');
    }
};
