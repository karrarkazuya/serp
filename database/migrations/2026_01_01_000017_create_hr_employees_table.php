<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // hr_employees.contract_id FK added in migration 000019 (circular: employees → contracts → employees)
        Schema::create('hr_employees', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('name_en')->nullable();
            $table->string('family_name')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('employee_code')->nullable()->unique();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('avatar')->nullable();
            $table->string('barcode')->nullable()->unique();
            $table->string('pin_code')->nullable();
            $table->text('notes')->nullable();

            $table->string('work_email')->nullable();
            $table->string('work_phone')->nullable();
            $table->string('work_mobile')->nullable();
            $table->string('job_title')->nullable();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->foreignId('job_id')->nullable()->constrained('hr_jobs')->nullOnDelete();
            $table->foreignId('work_location_id')->nullable()->constrained('hr_work_locations')->nullOnDelete();
            $table->foreignId('resource_calendar_id')->nullable()->constrained('hr_resource_calendars')->nullOnDelete();
            $table->string('timezone')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->foreignId('coach_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->foreignId('expense_manager_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->foreignId('attendance_manager_id')->nullable()->constrained('hr_employees')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();

            $table->string('private_email')->nullable();
            $table->string('private_phone')->nullable();
            $table->string('private_mobile')->nullable();
            $table->text('private_address')->nullable();
            $table->unsignedInteger('km_home_work')->nullable();
            $table->string('private_car_plate')->nullable();
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('zip')->nullable();
            $table->string('nationality')->nullable();
            $table->string('identification_id')->nullable();
            $table->string('passport_id')->nullable();
            $table->string('ssnid')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->date('birthday')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->string('country_of_birth')->nullable();
            $table->enum('marital_status', ['single', 'married', 'cohabitant', 'widower', 'divorced'])->nullable();
            $table->string('spouse_name')->nullable();
            $table->date('spouse_birthdate')->nullable();
            $table->unsignedInteger('children')->default(0);
            $table->enum('certificate_level', ['none', 'graduate', 'bachelor', 'master', 'doctor', 'other'])->nullable();
            $table->string('study_field')->nullable();
            $table->string('study_school')->nullable();
            $table->string('visa_no')->nullable();
            $table->string('work_permit_no')->nullable();
            $table->date('visa_expire')->nullable();
            $table->date('work_permit_expiration_date')->nullable();
            $table->string('work_permit_file')->nullable();

            $table->enum('employment_status', ['draft', 'active', 'probation', 'suspended', 'resigned', 'terminated'])->default('active');
            $table->date('hire_date')->nullable();
            $table->date('first_contract_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('departure_date')->nullable();
            $table->foreignId('departure_reason_id')->nullable()->constrained('hr_departure_reasons')->nullOnDelete();
            $table->text('departure_description')->nullable();
            $table->date('probation_start_date')->nullable();
            $table->date('probation_end_date')->nullable();

            $table->unsignedBigInteger('contract_id')->nullable();
            $table->decimal('wage', 15, 2)->nullable();
            $table->enum('payment_method', ['cash', 'bank_transfer', 'cheque'])->nullable();

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
