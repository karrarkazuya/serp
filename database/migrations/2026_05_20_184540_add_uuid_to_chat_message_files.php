<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_message_files', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        // Backfill existing rows before adding the unique constraint
        DB::table('chat_message_files')->whereNull('uuid')->orderBy('id')->each(function ($row) {
            DB::table('chat_message_files')->where('id', $row->id)->update(['uuid' => Str::uuid()->toString()]);
        });

        Schema::table('chat_message_files', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('chat_message_files', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
