<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_attendances', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            // Snapshot of the calendar the employee was on for this day.
            $table->foreignId('resource_calendar_id')->nullable()->constrained('hr_resource_calendars')->nullOnDelete();

            $table->date('attendance_date');

            $table->dateTime('check_in')->nullable();
            $table->dateTime('check_out')->nullable();

            // Expected punch times for this day (derived from the schedule)
            $table->dateTime('expected_check_in')->nullable();
            $table->dateTime('expected_check_out')->nullable();

            // Computed metrics
            $table->decimal('expected_hours', 5, 2)->default(0);
            $table->decimal('worked_hours',   5, 2)->default(0);
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->decimal('shortage_hours', 5, 2)->default(0);

            // State flags
            $table->boolean('is_day_off')->default(false);
            $table->boolean('is_absence')->default(false);

            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'attendance_date'], 'hr_attendances_employee_date_uq');
            $table->index(['company_id', 'attendance_date']);
            $table->index('check_in');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_attendances');
    }
};
