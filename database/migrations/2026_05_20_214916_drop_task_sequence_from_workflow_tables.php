<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_tickets', function (Blueprint $table) {
            $table->dropColumn('task_sequence');
        });

        Schema::table('workflow_procedure_steps', function (Blueprint $table) {
            $table->dropColumn('task_sequence');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_tickets', function (Blueprint $table) {
            $table->unsignedInteger('task_sequence')->default(0)->after('name');
        });

        Schema::table('workflow_procedure_steps', function (Blueprint $table) {
            $table->unsignedInteger('task_sequence')->default(0)->after('name');
        });
    }
};
