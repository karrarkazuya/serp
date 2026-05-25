<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Configuration: subtypes (per company OR global with company_id null).
        Schema::create('hr_request_subtypes', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->enum('type', ['leave', 'time_off', 'overtime']);
            $table->boolean('cuts_salary')->default(false);
            $table->boolean('cuts_balance')->default(false);
            // Only meaningful for overtime; service+UI force this to 1.0 for other types.
            $table->decimal('factor', 5, 2)->default(1.00);
            $table->boolean('requires_title')->default(true);
            $table->boolean('requires_description')->default(false);
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('active')->default(true);
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'active']);
            $table->index('company_id');
            // Prevent duplicate subtypes within the same scope (company or global).
            $table->unique(['company_id', 'name'], 'hr_request_subtypes_scope_name_uq');
        });

        // Per-company balance accumulator config.
        Schema::create('hr_request_balance_configs', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete()->unique();
            $table->decimal('leave_days_per_month', 6, 2)->default(0);
            $table->decimal('leave_days_max',       6, 2)->default(0);
            $table->decimal('time_off_hours_per_month', 6, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // Per-employee live balances. Cron uses last_credited_month to catch up.
        Schema::create('hr_employee_balances', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete()->unique();
            $table->decimal('leave_days_balance',    8, 2)->default(0);
            $table->decimal('time_off_hours_balance', 8, 2)->default(0);
            // First of the month for which credit was last applied (e.g. 2026-05-01).
            $table->date('last_credited_month')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // The request itself.
        Schema::create('hr_employee_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->enum('type', ['leave', 'time_off', 'overtime']);
            // restrict (not cascade) so deleting a subtype can never sweep
            // requests away — requests are immutable per spec.
            $table->foreignId('subtype_id')->constrained('hr_request_subtypes')->restrictOnDelete();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->decimal('duration_days',  8, 2)->default(0); // populated for leave
            $table->decimal('duration_hours', 8, 2)->default(0); // populated for time_off + overtime
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('attachment')->nullable(); // FileService UUID

            // Two-stage approval. Each side independently records its decision.
            $table->enum('manager_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->dateTime('manager_decision_at')->nullable();
            $table->foreignId('manager_decision_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('manager_decision_reason')->nullable();

            $table->enum('hr_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->dateTime('hr_decision_at')->nullable();
            $table->foreignId('hr_decision_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('hr_decision_reason')->nullable();

            // Cached derived state for fast listing / filtering. Service keeps it
            // in sync with manager_status + hr_status (HR rejection or manager
            // rejection => rejected; HR approval => approved; else pending).
            $table->enum('state', ['pending', 'approved', 'rejected'])->default('pending');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'state']);
            $table->index(['company_id', 'state']);
            $table->index(['type', 'state']);
            $table->index('start_at');
        });

        // Attendance gets a back-link to the approved request that caused the
        // adjustment, plus a separate bucket for approved-overtime hours.
        Schema::table('hr_attendances', function (Blueprint $table) {
            $table->foreignId('request_id')->nullable()->after('resource_calendar_id')
                ->constrained('hr_employee_requests')->nullOnDelete();
            $table->decimal('approved_overtime_hours', 5, 2)->default(0)->after('overtime_hours');
        });
    }

    public function down(): void
    {
        Schema::table('hr_attendances', function (Blueprint $table) {
            $table->dropForeign(['request_id']);
            $table->dropColumn(['request_id', 'approved_overtime_hours']);
        });
        Schema::dropIfExists('hr_employee_requests');
        Schema::dropIfExists('hr_employee_balances');
        Schema::dropIfExists('hr_request_balance_configs');
        Schema::dropIfExists('hr_request_subtypes');
    }
};
