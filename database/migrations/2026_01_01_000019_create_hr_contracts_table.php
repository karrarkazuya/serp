<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_contracts', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->foreignId('job_id')->nullable()->constrained('hr_jobs')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('resource_calendar_id')->nullable()->constrained('hr_resource_calendars')->nullOnDelete();
            $table->date('date_start')->nullable();
            $table->date('date_end')->nullable();
            $table->date('trial_date_start')->nullable();
            $table->date('trial_date_end')->nullable();
            $table->enum('state', ['draft', 'open', 'close', 'cancelled'])->default('draft');
            $table->enum('contract_type', ['full_time', 'part_time', 'temporary', 'internship', 'contractor'])->default('full_time');
            $table->decimal('wage', 15, 2)->nullable();
            $table->string('currency')->default('IQD');
            $table->text('notes')->nullable();
            $table->string('image')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'state']);
            $table->index(['company_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_contracts');
    }
};
