<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Template-level config: which other steps should also see this input (read-only)
        Schema::create('workflow_template_input_guest_steps', function (Blueprint $table) {
            $table->unsignedBigInteger('template_input_id');
            $table->unsignedBigInteger('guest_step_id');
            $table->primary(['template_input_id', 'guest_step_id']);
            $table->foreign('template_input_id')->references('id')->on('workflow_template_inputs')->onDelete('cascade');
            $table->foreign('guest_step_id')->references('id')->on('workflow_procedure_steps')->onDelete('cascade');
        });

        // Live cross-refs frozen at procedure creation
        Schema::create('workflow_ticket_input_refs', function (Blueprint $table) {
            $table->unsignedBigInteger('viewing_ticket_id');
            $table->unsignedBigInteger('source_record_input_id');
            $table->primary(['viewing_ticket_id', 'source_record_input_id']);
            $table->foreign('viewing_ticket_id')->references('id')->on('workflow_tickets')->onDelete('cascade');
            $table->foreign('source_record_input_id')->references('id')->on('workflow_record_inputs')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_ticket_input_refs');
        Schema::dropIfExists('workflow_template_input_guest_steps');
    }
};
