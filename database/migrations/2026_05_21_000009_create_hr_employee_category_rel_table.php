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
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employee_category_rel');
    }
};
