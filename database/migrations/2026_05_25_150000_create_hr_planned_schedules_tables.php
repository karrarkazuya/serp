<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Forward-looking buffer of planned working schedules per employee.
        // Maintained at a rolling 30 days by syncMissingDays().
        Schema::create('hr_planned_days', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('resource_calendar_id')->nullable()->constrained('hr_resource_calendars')->nullOnDelete();
            $table->date('planned_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'planned_date'], 'hr_planned_days_emp_date_uq');
            $table->index('planned_date');
        });

        // Repeating pattern per employee. Ordered by `sequence`. Empty = no pattern.
        Schema::create('hr_planned_rschedules', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('resource_calendar_id')->constrained('hr_resource_calendars')->cascadeOnDelete();
            $table->unsignedInteger('sequence')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'sequence']);
        });

        // Cron idempotency tracker. One row per successful run.
        Schema::create('hr_planned_schedule_runs', function (Blueprint $table) {
            $table->id();
            $table->date('run_date')->unique();
            $table->timestamp('ran_at');
            $table->boolean('success')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_planned_schedule_runs');
        Schema::dropIfExists('hr_planned_rschedules');
        Schema::dropIfExists('hr_planned_days');
    }
};
