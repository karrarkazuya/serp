<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enrich employees table (columns may already exist from prior run)
        Schema::table('hr_employees', function (Blueprint $table) {
            if (!Schema::hasColumn('hr_employees', 'scientific_title')) {
                $table->string('scientific_title')->nullable()->after('job_title');
            }
            if (!Schema::hasColumn('hr_employees', 'organizational_structure')) {
                $table->string('organizational_structure')->nullable()->after('scientific_title');
            }
            if (!Schema::hasColumn('hr_employees', 'assignment_type')) {
                $table->string('assignment_type')->nullable()->after('organizational_structure');
            }
            if (!Schema::hasColumn('hr_employees', 'data_status')) {
                $table->enum('data_status', ['current', 'previous'])->nullable()->after('assignment_type');
            }
            if (!Schema::hasColumn('hr_employees', 'financial_specialization')) {
                $table->decimal('financial_specialization', 15, 2)->nullable()->after('data_status');
            }
        });

        // Enrich employee documents table
        Schema::table('hr_employee_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('hr_employee_documents', 'issued_by')) {
                $table->string('issued_by')->nullable()->after('document_type');
            }
            if (!Schema::hasColumn('hr_employee_documents', 'document_number')) {
                $table->string('document_number')->nullable()->after('issued_by');
            }
            if (!Schema::hasColumn('hr_employee_documents', 'organizational_structure')) {
                $table->string('organizational_structure')->nullable()->after('document_number');
            }
            if (!Schema::hasColumn('hr_employee_documents', 'active')) {
                $table->boolean('active')->default(true)->after('notes');
            }
        });

        // New certificates table
        Schema::create('hr_employee_certificates', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->string('certificate_type')->nullable();
            $table->string('study_type')->nullable();
            $table->string('issuing_institution')->nullable();
            $table->string('country')->nullable();
            $table->enum('data_status', ['current', 'previous'])->nullable();
            $table->date('graduate_date')->nullable();
            $table->decimal('financial_specialization', 15, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employee_certificates');

        Schema::table('hr_employee_documents', function (Blueprint $table) {
            $table->dropColumn(['issued_by', 'document_number', 'organizational_structure', 'active']);
        });

        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropColumn(['scientific_title', 'organizational_structure', 'assignment_type', 'data_status', 'financial_specialization']);
        });
    }
};
