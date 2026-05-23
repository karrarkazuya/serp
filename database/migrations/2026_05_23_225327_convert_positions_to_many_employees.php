<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Positions are now standalone — drop the single employee FK + index
        Schema::table('hr_employee_positions', function (Blueprint $table) {
            $table->dropIndex(['employee_id']);
            $table->dropForeign(['employee_id']);
            $table->dropColumn('employee_id');
        });

        // Pure pivot — no id, no softDeletes per project rules
        Schema::create('hr_position_employees', function (Blueprint $table) {
            $table->unsignedBigInteger('position_id');
            $table->unsignedBigInteger('employee_id');
            $table->primary(['position_id', 'employee_id']);
            $table->foreign('position_id')->references('id')->on('hr_employee_positions')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('hr_employees')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_position_employees');

        Schema::table('hr_employee_positions', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_id')->nullable()->after('uuid');
            $table->foreign('employee_id')->references('id')->on('hr_employees')->onDelete('set null');
        });
    }
};
