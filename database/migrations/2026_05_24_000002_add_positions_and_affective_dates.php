<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add affective_date to certificates
        Schema::table('hr_employee_certificates', function (Blueprint $table) {
            $table->date('affective_date')->nullable()->after('graduate_date');
        });

        // Create positions table (multi-record per employee)
        Schema::create('hr_employee_positions', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->string('organizational_structure')->nullable();
            $table->string('assignment_type')->nullable();
            $table->enum('data_status', ['current', 'previous'])->nullable();
            $table->decimal('financial_specialization', 15, 2)->nullable();
            $table->date('affective_date')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('employee_id');
            $table->index('affective_date');
        });

        // Drop the now-superseded per-employee position columns
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropColumn(['organizational_structure', 'assignment_type', 'data_status', 'financial_specialization']);
        });
    }

    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->string('organizational_structure')->nullable();
            $table->string('assignment_type')->nullable();
            $table->enum('data_status', ['current', 'previous'])->nullable();
            $table->decimal('financial_specialization', 15, 2)->nullable();
        });

        Schema::dropIfExists('hr_employee_positions');

        Schema::table('hr_employee_certificates', function (Blueprint $table) {
            $table->dropColumn('affective_date');
        });
    }
};
