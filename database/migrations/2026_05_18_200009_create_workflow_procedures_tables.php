<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workflow_procedures', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('procedure_template_id')->constrained('workflow_procedure_templates')->restrictOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('state')->default('pending'); // pending, completed, closed
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('resolve_max_duration')->default(168);
            $table->dateTime('resolve_deadline')->nullable();
            $table->integer('resolve_duration')->default(0);
            $table->integer('resolve_deadline_passed')->default(0);
            // optional links
            $table->foreignId('optional_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->unsignedBigInteger('optional_ticket_id')->nullable();
            $table->unsignedBigInteger('optional_procedure_id')->nullable(); // parent procedure (self)
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('workflow_procedure_viewers', function (Blueprint $table) {
            $table->foreignId('procedure_id')->constrained('workflow_procedures')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['procedure_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_procedure_viewers');
        Schema::dropIfExists('workflow_procedures');
    }
};
