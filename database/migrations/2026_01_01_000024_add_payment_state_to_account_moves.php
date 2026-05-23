<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('account_moves', 'payment_state')) {
            Schema::table('account_moves', function (Blueprint $table) {
                $table->string('payment_state', 16)->default('not_paid')->after('state');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('account_moves', 'payment_state')) {
            Schema::table('account_moves', function (Blueprint $table) {
                $table->dropColumn('payment_state');
            });
        }
    }
};
