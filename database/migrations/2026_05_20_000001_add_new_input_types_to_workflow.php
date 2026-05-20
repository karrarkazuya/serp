<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('workflow_record_inputs', function (Blueprint $table) {
            $table->decimal('value_float', 12, 4)->nullable()->after('value_boolean');
            $table->text('value_text')->nullable()->after('value_float');
            $table->string('value_file_path')->nullable()->after('value_text');
            $table->string('value_file_name')->nullable()->after('value_file_path');
            $table->string('value_file_mime', 100)->nullable()->after('value_file_name');
            $table->unsignedBigInteger('value_file_size')->nullable()->after('value_file_mime');
        });

        Schema::create('workflow_record_input_multiselect', function (Blueprint $table) {
            $table->foreignId('record_input_id')
                ->constrained('workflow_record_inputs')
                ->cascadeOnDelete();
            $table->foreignId('option_id')
                ->constrained('workflow_template_input_options')
                ->cascadeOnDelete();
            $table->primary(['record_input_id', 'option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_record_input_multiselect');

        Schema::table('workflow_record_inputs', function (Blueprint $table) {
            $table->dropColumn([
                'value_float', 'value_text',
                'value_file_path', 'value_file_name', 'value_file_mime', 'value_file_size',
            ]);
        });
    }
};
