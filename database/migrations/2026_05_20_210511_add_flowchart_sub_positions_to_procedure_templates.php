<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_procedure_templates', function (Blueprint $table) {
            $table->json('flowchart_sub_positions')->nullable()->after('updated_by');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_procedure_templates', function (Blueprint $table) {
            $table->dropColumn('flowchart_sub_positions');
        });
    }
};
