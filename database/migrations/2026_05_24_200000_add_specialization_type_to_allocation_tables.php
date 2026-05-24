<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['hr_employee_bonuses', 'hr_employee_appreciations', 'hr_employee_rewards'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('specialization_type')->default('amount')->after('financial_specialization');
                $table->unsignedInteger('employee_seniority')->nullable()->after('specialization_type');
            });
        }
    }

    public function down(): void
    {
        foreach (['hr_employee_bonuses', 'hr_employee_appreciations', 'hr_employee_rewards'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(['specialization_type', 'employee_seniority']);
            });
        }
    }
};
