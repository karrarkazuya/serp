<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('avatar')->nullable()->after('notes');
            $table->foreignId('parent_id')->nullable()->constrained('contacts')->nullOnDelete()->after('company_id');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['avatar', 'parent_id']);
        });
    }
};
