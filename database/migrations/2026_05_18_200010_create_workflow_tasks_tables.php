<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add procedure FK columns to workflow_tickets
        Schema::table('workflow_tickets', function (Blueprint $table) {
            $table->foreignId('procedure_id')->nullable()->after('template_id')->constrained('workflow_procedures')->nullOnDelete();
            $table->foreignId('procedure_step_id')->nullable()->after('procedure_id')->constrained('workflow_procedure_steps')->nullOnDelete();
            $table->unsignedBigInteger('previous_ticket_id')->nullable()->after('procedure_step_id');
        });

        // Self-referential FK for previous_ticket_id
        Schema::table('workflow_tickets', function (Blueprint $table) {
            $table->foreign('previous_ticket_id')->references('id')->on('workflow_tickets')->nullOnDelete();
        });

        // Ticket ordering within a procedure (next tickets after this one)
        Schema::create('workflow_ticket_next', function (Blueprint $table) {
            $table->foreignId('ticket_id')->constrained('workflow_tickets')->cascadeOnDelete();
            $table->foreignId('next_ticket_id')->constrained('workflow_tickets')->cascadeOnDelete();
            $table->primary(['ticket_id', 'next_ticket_id']);
        });

        // Path choices for conditional ticket routing within a procedure
        Schema::create('workflow_ticket_paths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('workflow_tickets')->cascadeOnDelete();
            $table->foreignId('target_ticket_id')->constrained('workflow_tickets')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        // Add path_chosen_id FK after the paths table exists
        Schema::table('workflow_tickets', function (Blueprint $table) {
            $table->foreignId('path_chosen_id')->nullable()->after('previous_ticket_id');
            $table->foreign('path_chosen_id')->references('id')->on('workflow_ticket_paths')->nullOnDelete();
        });

        // Sub-procedure lines for procedure-tickets
        Schema::create('workflow_ticket_procedure_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('ticket_id')->constrained('workflow_tickets')->cascadeOnDelete();
            $table->foreignId('procedure_template_id')->constrained('workflow_procedure_templates')->restrictOnDelete();
            $table->string('name');
            $table->string('state')->default('pending'); // pending, completed
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_ticket_procedure_lines');
        Schema::table('workflow_tickets', function (Blueprint $table) {
            $table->dropForeign(['path_chosen_id']);
            $table->dropColumn('path_chosen_id');
        });
        Schema::dropIfExists('workflow_ticket_paths');
        Schema::dropIfExists('workflow_ticket_next');
        Schema::table('workflow_tickets', function (Blueprint $table) {
            $table->dropForeign(['previous_ticket_id']);
            $table->dropForeign(['procedure_step_id']);
            $table->dropForeign(['procedure_id']);
            $table->dropColumn(['previous_ticket_id', 'procedure_step_id', 'procedure_id']);
        });
    }
};
