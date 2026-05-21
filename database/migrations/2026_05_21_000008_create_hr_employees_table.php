<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_employees', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            // Basic Information
            $table->string('name');
            $table->string('employee_code')->nullable()->unique();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('avatar')->nullable();
            $table->string('barcode')->nullable()->unique();
            $table->string('pin_code')->nullable();
            $table->text('notes')->nullable();

            // Work Information
            $table->string('work_email')->nullable();
            $table->string('work_phone')->nullable();
            $table->string('work_mobile')->nullable();
            $table->string('job_title')->nullable();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->foreignId('job_id')->nullable()->constrained('hr_jobs')->nullOnDelete();
            $table->foreignId('work_location_id')->nullable()->constrained('hr_work_locations')->nullOnDelete();
            $table->foreignId('resource_calendar_id')->nullable()->constrained('hr_resource_calendars')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('hr_employees')->nullOnDelete(); // direct manager
            $table->foreignId('coach_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Private Information
            $table->string('private_email')->nullable();
            $table->string('private_phone')->nullable();
            $table->string('private_mobile')->nullable();
            $table->text('private_address')->nullable();
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('zip')->nullable();
            $table->string('nationality')->nullable();
            $table->string('identification_id')->nullable();
            $table->string('passport_id')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->date('birthday')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->string('country_of_birth')->nullable();
            $table->enum('marital_status', ['single', 'married', 'cohabitant', 'widower', 'divorced'])->nullable();
            $table->string('spouse_name')->nullable();
            $table->date('spouse_birthdate')->nullable();
            $table->unsignedInteger('children')->default(0);

            // HR Status
            $table->enum('employment_status', ['draft', 'active', 'probation', 'suspended', 'resigned', 'terminated'])->default('active');
            $table->date('hire_date')->nullable();
            $table->date('first_contract_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('departure_date')->nullable();
            $table->foreignId('departure_reason_id')->nullable()->constrained('hr_departure_reasons')->nullOnDelete();
            $table->text('departure_description')->nullable();
            $table->date('probation_start_date')->nullable();
            $table->date('probation_end_date')->nullable();

            // Payroll / Contract Link — FK added after hr_contracts created (migration 000016)
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->decimal('wage', 15, 2)->nullable();
            $table->enum('payment_method', ['cash', 'bank_transfer', 'cheque'])->nullable();

            // Emergency quick fields
            $table->string('emergency_contact')->nullable();
            $table->string('emergency_phone')->nullable();
            $table->string('emergency_relation')->nullable();

            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['active', 'company_id']);
            $table->index('department_id');
            $table->index('parent_id');
            $table->index('employment_status');
            $table->index('work_email');
            $table->index('contract_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employees');
    }
};
