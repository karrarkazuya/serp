<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_template_inputs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->unsignedBigInteger('owner_id');
            $table->string('owner_type', 20);
            $table->string('name');
            $table->string('type')->default('char');
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
            $table->string('record_type', 20);
            $table->foreignId('template_input_id')->nullable()->constrained('workflow_template_inputs')->nullOnDelete();
            $table->string('name');
            $table->string('type')->default('char');
            $table->string('value_char')->nullable();
            $table->integer('value_int')->nullable();
            $table->date('value_date')->nullable();
            $table->dateTime('value_datetime')->nullable();
            $table->boolean('value_boolean')->default(false);
            $table->decimal('value_float', 12, 4)->nullable();
            $table->text('value_text')->nullable();
            $table->string('value_file_path')->nullable();
            $table->string('value_file_name')->nullable();
            $table->string('value_file_mime', 100)->nullable();
            $table->unsignedBigInteger('value_file_size')->nullable();
            $table->foreignId('value_select_id')->nullable()->constrained('workflow_template_input_options')->nullOnDelete();
            $table->boolean('is_required')->default(true);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['record_id', 'record_type'], 'wri_record_idx');
        });

        Schema::create('workflow_record_input_multiselect', function (Blueprint $table) {
            $table->foreignId('record_input_id')->constrained('workflow_record_inputs')->cascadeOnDelete();
            $table->foreignId('option_id')->constrained('workflow_template_input_options')->cascadeOnDelete();
            $table->primary(['record_input_id', 'option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_record_input_multiselect');
        Schema::dropIfExists('workflow_record_inputs');
        Schema::dropIfExists('workflow_template_input_options');
        Schema::dropIfExists('workflow_template_inputs');
    }
};
