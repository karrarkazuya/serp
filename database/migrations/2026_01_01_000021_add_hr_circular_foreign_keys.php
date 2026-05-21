<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Deferred circular FKs for the HR module:
// hr_departments.manager_id → hr_employees.id  (departments must exist before employees, employees before contracts)
// hr_employees.contract_id → hr_contracts.id   (contracts must exist before this FK)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_departments', function (Blueprint $table) {
            $table->foreign('manager_id')->references('id')->on('hr_employees')->nullOnDelete();
        });

        Schema::table('hr_employees', function (Blueprint $table) {
            $table->foreign('contract_id')->references('id')->on('hr_contracts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropForeign(['contract_id']);
        });

        Schema::table('hr_departments', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });
    }
};
