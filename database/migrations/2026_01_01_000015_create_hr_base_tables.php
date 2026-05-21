<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_departure_reasons', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('hr_employee_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->string('color')->default('#6366f1');
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // hr_departments.manager_id FK added in migration 000019 (circular: departments → employees → departments)
        Schema::create('hr_departments', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->text('note')->nullable();
            $table->integer('color_index')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['active', 'company_id']);
            $table->index('manager_id');
        });

        Schema::create('hr_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->integer('expected_employees')->default(1);
            $table->integer('no_of_recruitment')->default(0);
            $table->enum('state', ['open', 'recruitment', 'closed'])->default('open');
            $table->boolean('active')->default(true);
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['active', 'company_id']);
        });

        Schema::create('hr_work_locations', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['active', 'company_id']);
        });

        Schema::create('hr_resource_calendars', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->string('timezone')->default('UTC');
            $table->decimal('hours_per_day', 5, 2)->default(8.00);
            $table->decimal('company_hours_per_week', 5, 2)->nullable();
            $table->boolean('flexible_hours')->default(false);
            $table->boolean('active')->default(true);
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['active', 'company_id']);
        });

        Schema::create('hr_resource_calendar_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_id')->constrained('hr_resource_calendars')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->decimal('hour_from', 5, 2)->default(9.00);
            $table->decimal('hour_to', 5, 2)->default(17.00);
            $table->enum('day_period', ['morning', 'afternoon', 'evening'])->default('morning');
            $table->integer('sequence')->default(0);
            $table->timestamps();

            $table->index('calendar_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_resource_calendar_attendances');
        Schema::dropIfExists('hr_resource_calendars');
        Schema::dropIfExists('hr_work_locations');
        Schema::dropIfExists('hr_jobs');
        Schema::dropIfExists('hr_departments');
        Schema::dropIfExists('hr_employee_categories');
        Schema::dropIfExists('hr_departure_reasons');
    }
};
