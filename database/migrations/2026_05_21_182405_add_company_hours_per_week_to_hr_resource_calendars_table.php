<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('hr_resource_calendars', function (Blueprint $table) {
            $table->decimal('company_hours_per_week', 5, 2)->nullable()->after('hours_per_day');
        });
    }

    public function down(): void
    {
        Schema::table('hr_resource_calendars', function (Blueprint $table) {
            $table->dropColumn('company_hours_per_week');
        });
    }
};
