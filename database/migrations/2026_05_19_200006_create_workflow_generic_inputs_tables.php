<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workflow_template_inputs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->unsignedBigInteger('owner_id');
            $table->string('owner_type', 20); // ticket_template | template_task
            $table->string('name');
            $table->string('type')->default('char'); // char, int, date, datetime, boolean, select, label
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['owner_id', 'owner_type'], 'wti_owner_idx');
        });

        Schema::create('workflow_template_input_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_input_id')->constrained('workflow_template_inputs')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('workflow_record_inputs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->unsignedBigInteger('record_id');
            $table->string('record_type', 20); // ticket | task
            $table->foreignId('template_input_id')->nullable()->constrained('workflow_template_inputs')->nullOnDelete();
            $table->string('name');
            $table->string('type')->default('char');
            $table->string('value_char')->nullable();
            $table->integer('value_int')->nullable();
            $table->date('value_date')->nullable();
            $table->dateTime('value_datetime')->nullable();
            $table->boolean('value_boolean')->default(false);
            $table->foreignId('value_select_id')->nullable()->constrained('workflow_template_input_options')->nullOnDelete();
            $table->boolean('is_required')->default(true);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['record_id', 'record_type'], 'wri_record_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_record_inputs');
        Schema::dropIfExists('workflow_template_input_options');
        Schema::dropIfExists('workflow_template_inputs');
    }
};
