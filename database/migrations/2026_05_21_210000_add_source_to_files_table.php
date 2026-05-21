<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            // Table name and row ID of the record that owns this file.
            // Used by the garbage collector to detect orphaned files after source deletion.
            $table->string('source_type', 100)->nullable()->after('context_id');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->index(['source_type', 'source_id'], 'files_source_index');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropIndex('files_source_index');
            $table->dropColumn(['source_type', 'source_id']);
        });
    }
};
