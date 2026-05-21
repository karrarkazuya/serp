<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Deferred because workflow_tickets and workflow_ticket_paths have a circular FK:
// workflow_ticket_paths.ticket_id → workflow_tickets.id
// workflow_tickets.path_chosen_id → workflow_ticket_paths.id
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('path_chosen_id')->nullable();
            $table->foreign('path_chosen_id')->references('id')->on('workflow_ticket_paths')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workflow_tickets', function (Blueprint $table) {
            $table->dropForeign(['path_chosen_id']);
            $table->dropColumn('path_chosen_id');
        });
    }
};
