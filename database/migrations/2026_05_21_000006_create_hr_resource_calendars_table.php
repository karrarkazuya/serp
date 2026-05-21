<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_resource_calendars', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->string('timezone')->default('UTC');
            $table->decimal('hours_per_day', 5, 2)->default(8.00);
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
            $table->unsignedTinyInteger('day_of_week'); // 0=Saturday ... 6=Friday
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
    }
};
