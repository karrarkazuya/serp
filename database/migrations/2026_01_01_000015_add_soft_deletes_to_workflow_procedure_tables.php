<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_procedure_templates', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('workflow_procedure_steps', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('workflow_procedure_steps', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('workflow_procedure_templates', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
