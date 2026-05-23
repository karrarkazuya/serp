<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_moves', function (Blueprint $table) {
            $table->date('invoice_date_due')->nullable()->after('date');
            $table->foreignId('payment_term_id')->nullable()->constrained('accounting_payment_terms')->nullOnDelete()->after('invoice_date_due');
            $table->foreignId('incoterm_id')->nullable()->constrained('accounting_incoterms')->nullOnDelete()->after('payment_term_id');
            $table->string('invoice_origin', 128)->nullable()->after('incoterm_id');
        });
    }

    public function down(): void
    {
        Schema::table('account_moves', function (Blueprint $table) {
            $table->dropForeign(['payment_term_id']);
            $table->dropForeign(['incoterm_id']);
            $table->dropColumn(['invoice_date_due', 'payment_term_id', 'incoterm_id', 'invoice_origin']);
        });
    }
};
