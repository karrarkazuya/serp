<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->string('name_en')->nullable()->after('name_ar');
            $table->string('family_name')->nullable()->after('name_en');
            $table->string('mother_name')->nullable()->after('family_name');

            $table->string('timezone')->nullable()->after('resource_calendar_id');

            $table->foreignId('expense_manager_id')->nullable()->after('coach_id')->constrained('hr_employees')->nullOnDelete();
            $table->foreignId('attendance_manager_id')->nullable()->after('expense_manager_id')->constrained('hr_employees')->nullOnDelete();

            $table->string('ssnid')->nullable()->after('passport_id');

            $table->unsignedInteger('km_home_work')->nullable()->after('private_mobile');
            $table->string('private_car_plate')->nullable()->after('km_home_work');

            $table->enum('certificate_level', ['none', 'graduate', 'bachelor', 'master', 'doctor', 'other'])->nullable()->after('country_of_birth');
            $table->string('study_field')->nullable()->after('certificate_level');
            $table->string('study_school')->nullable()->after('study_field');

            $table->string('visa_no')->nullable()->after('study_school');
            $table->string('work_permit_no')->nullable()->after('visa_no');
            $table->date('visa_expire')->nullable()->after('work_permit_no');
            $table->date('work_permit_expiration_date')->nullable()->after('visa_expire');
            $table->string('work_permit_file')->nullable()->after('work_permit_expiration_date');
        });
    }

    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropForeign(['expense_manager_id']);
            $table->dropForeign(['attendance_manager_id']);
            $table->dropColumn([
                'name_ar', 'name_en', 'family_name', 'mother_name',
                'timezone', 'expense_manager_id', 'attendance_manager_id',
                'ssnid', 'km_home_work', 'private_car_plate',
                'certificate_level', 'study_field', 'study_school',
                'visa_no', 'work_permit_no', 'visa_expire',
                'work_permit_expiration_date', 'work_permit_file',
            ]);
        });
    }
};
