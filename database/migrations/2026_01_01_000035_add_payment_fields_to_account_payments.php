<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_payments', function (Blueprint $table) {
            $table->string('state', 16)->default('draft')->after('memo');
            $table->string('payment_method', 64)->default('manual')->after('state');
            $table->string('bank_reference', 255)->nullable()->after('payment_method');
            $table->string('cheque_number', 255)->nullable()->after('bank_reference');
            $table->foreignId('destination_account_id')->nullable()->after('cheque_number')
                  ->constrained('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('account_payments', function (Blueprint $table) {
            $table->dropForeign(['destination_account_id']);
            $table->dropColumn(['state', 'payment_method', 'bank_reference', 'cheque_number', 'destination_account_id']);
        });
    }
};
