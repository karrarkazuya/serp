<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_employee_category_rel', function (Blueprint $table) {
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('hr_employee_categories')->cascadeOnDelete();
            $table->primary(['employee_id', 'category_id']);
        });

        Schema::create('hr_employee_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained('hr_skills')->cascadeOnDelete();
            $table->foreignId('skill_type_id')->constrained('hr_skill_types')->cascadeOnDelete();
            $table->foreignId('skill_level_id')->nullable()->constrained('hr_skill_levels')->nullOnDelete();
            $table->decimal('years_of_experience', 5, 1)->nullable();
            $table->timestamps();

            $table->index('employee_id');
            $table->unique(['employee_id', 'skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employee_skills');
        Schema::dropIfExists('hr_employee_category_rel');
    }
};
