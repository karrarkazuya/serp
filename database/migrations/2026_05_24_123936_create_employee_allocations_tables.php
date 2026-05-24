<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $shared = function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            // document fields (same as hr_employee_documents)
            $table->string('name')->nullable();
            $table->enum('document_type', ['contract', 'id_card', 'passport', 'certificate', 'resume', 'medical', 'other'])->nullable();
            $table->string('issued_by')->nullable();
            $table->string('document_number')->nullable();
            $table->string('file_path')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->unsignedSmallInteger('notify_before_days')->nullable();
            $table->text('notes')->nullable();
            // position fields (same as hr_employee_positions)
            $table->string('organizational_structure')->nullable();
            $table->string('assignment_type')->nullable();
            $table->enum('data_status', ['current', 'previous'])->nullable();
            $table->decimal('financial_specialization', 15, 2)->nullable();
            $table->date('affective_date')->nullable();
            // common
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('employee_id');
            $table->index('affective_date');
        };

        Schema::create('hr_employee_bonuses',        $shared);
        Schema::create('hr_employee_appreciations',  $shared);
        Schema::create('hr_employee_sanctions',      $shared);
        Schema::create('hr_employee_rewards',        $shared);

        Schema::create('hr_employee_job_grades', function (Blueprint $table) {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employee_job_grades');
        Schema::dropIfExists('hr_employee_rewards');
        Schema::dropIfExists('hr_employee_sanctions');
        Schema::dropIfExists('hr_employee_appreciations');
        Schema::dropIfExists('hr_employee_bonuses');
    }
};
