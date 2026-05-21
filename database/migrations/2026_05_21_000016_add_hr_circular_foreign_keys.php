<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add manager_id FK to hr_departments (hr_employees must exist first)
        Schema::table('hr_departments', function (Blueprint $table) {
            $table->foreign('manager_id')->references('id')->on('hr_employees')->nullOnDelete();
        });

        // Add contract_id FK to hr_employees (hr_contracts must exist first)
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
