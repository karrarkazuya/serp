<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Link a running procedure back to its parent procedure line
        Schema::table('workflow_ticket_procedure_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('procedure_id')->nullable()->after('procedure_template_id');
        });

        // Guard flag: ticket cannot be completed until all sub-procedures are completed
        Schema::table('workflow_tickets', function (Blueprint $table) {
            $table->boolean('procedures_required')->default(false)->after('has_procedures');
        });

        Schema::table('workflow_procedure_steps', function (Blueprint $table) {
            $table->boolean('procedures_required')->default(false)->after('has_procedures');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_ticket_procedure_lines', function (Blueprint $table) {
            $table->dropColumn('procedure_id');
        });

        Schema::table('workflow_tickets', function (Blueprint $table) {
            $table->dropColumn('procedures_required');
        });

        Schema::table('workflow_procedure_steps', function (Blueprint $table) {
            $table->dropColumn('procedures_required');
        });
    }
};
