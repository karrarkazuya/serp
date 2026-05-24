<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'hr_employee_bonuses',
            'hr_employee_appreciations',
            'hr_employee_sanctions',
            'hr_employee_rewards',
            'hr_employee_job_grades',
        ] as $tbl) {
            Schema::table($tbl, function (Blueprint $table) {
                $table->dropForeign(['employee_id']);
                $table->dropIndex(['employee_id']);
                $table->dropColumn('employee_id');
            });
        }

        Schema::create('hr_bonus_employees', function (Blueprint $table) {
            $table->unsignedBigInteger('bonus_id');
            $table->unsignedBigInteger('employee_id');
            $table->primary(['bonus_id', 'employee_id']);
            $table->foreign('bonus_id')->references('id')->on('hr_employee_bonuses')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('hr_employees')->cascadeOnDelete();
        });

        Schema::create('hr_appreciation_employees', function (Blueprint $table) {
            $table->unsignedBigInteger('appreciation_id');
            $table->unsignedBigInteger('employee_id');
            $table->primary(['appreciation_id', 'employee_id']);
            $table->foreign('appreciation_id')->references('id')->on('hr_employee_appreciations')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('hr_employees')->cascadeOnDelete();
        });

        Schema::create('hr_sanction_employees', function (Blueprint $table) {
            $table->unsignedBigInteger('sanction_id');
            $table->unsignedBigInteger('employee_id');
            $table->primary(['sanction_id', 'employee_id']);
            $table->foreign('sanction_id')->references('id')->on('hr_employee_sanctions')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('hr_employees')->cascadeOnDelete();
        });

        Schema::create('hr_reward_employees', function (Blueprint $table) {
            $table->unsignedBigInteger('reward_id');
            $table->unsignedBigInteger('employee_id');
            $table->primary(['reward_id', 'employee_id']);
            $table->foreign('reward_id')->references('id')->on('hr_employee_rewards')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('hr_employees')->cascadeOnDelete();
        });

        Schema::create('hr_job_grade_employees', function (Blueprint $table) {
            $table->unsignedBigInteger('job_grade_id');
            $table->unsignedBigInteger('employee_id');
            $table->primary(['job_grade_id', 'employee_id']);
            $table->foreign('job_grade_id')->references('id')->on('hr_employee_job_grades')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('hr_employees')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_job_grade_employees');
        Schema::dropIfExists('hr_reward_employees');
        Schema::dropIfExists('hr_sanction_employees');
        Schema::dropIfExists('hr_appreciation_employees');
        Schema::dropIfExists('hr_bonus_employees');

        foreach ([
            'hr_employee_bonuses',
            'hr_employee_appreciations',
            'hr_employee_sanctions',
            'hr_employee_rewards',
            'hr_employee_job_grades',
        ] as $tbl) {
            Schema::table($tbl, function (Blueprint $table) {
                $table->unsignedBigInteger('employee_id')->nullable()->after('uuid');
                $table->foreign('employee_id')->references('id')->on('hr_employees')->nullOnDelete();
                $table->index('employee_id');
            });
        }
    }
};
