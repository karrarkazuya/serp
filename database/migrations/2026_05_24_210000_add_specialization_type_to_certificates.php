<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hr_employee_certificates', function (Blueprint $table) {
            $table->string('specialization_type')->default('amount')->after('financial_specialization');
        });
    }

    public function down(): void
    {
        Schema::table('hr_employee_certificates', function (Blueprint $table) {
            $table->dropColumn('specialization_type');
        });
    }
};
