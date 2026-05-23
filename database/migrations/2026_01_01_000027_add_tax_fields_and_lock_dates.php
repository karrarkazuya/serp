<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add tax-related columns to account_move_lines
        Schema::table('account_move_lines', function (Blueprint $table) {
            $table->foreignId('tax_line_id')->nullable()->after('partner_id')
                ->constrained('account_taxes')->nullOnDelete();
            $table->decimal('tax_base_amount', 16, 4)->nullable()->after('tax_line_id');
        });

        // Add accounting lock dates to companies
        Schema::table('companies', function (Blueprint $table) {
            $table->date('accounting_period_lock_date')->nullable()->after('currency');
            $table->date('accounting_fiscal_year_lock_date')->nullable()->after('accounting_period_lock_date');
        });
    }

    public function down(): void
    {
        Schema::table('account_move_lines', function (Blueprint $table) {
            $table->dropForeign(['tax_line_id']);
            $table->dropColumn(['tax_line_id', 'tax_base_amount']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['accounting_period_lock_date', 'accounting_fiscal_year_lock_date']);
        });
    }
};
