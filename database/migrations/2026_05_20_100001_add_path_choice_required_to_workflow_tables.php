<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_tickets', function (Blueprint $table) {
            $table->boolean('path_choice_required')->default(false)->after('path_choice_question');
        });

        Schema::table('workflow_procedure_steps', function (Blueprint $table) {
            $table->boolean('path_choice_required')->default(false)->after('path_choice_question');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_tickets', function (Blueprint $table) {
            $table->dropColumn('path_choice_required');
        });

        Schema::table('workflow_procedure_steps', function (Blueprint $table) {
            $table->dropColumn('path_choice_required');
        });
    }
};
