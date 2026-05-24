<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-currency, Odoo style.
 *
 * Adds:
 *   - `currencies` lookup table (code, name, symbol, position, decimal_places,
 *     rounding, active) — replaces the loose string codes we had in
 *     `companies.currency`, `account_moves.currency`, etc. (those columns
 *     keep their string form for back-compat; the lookup gives us
 *     formatting metadata, the currency picker UX, and per-currency rounding)
 *   - `company_currencies` pivot — which currencies each company permits in
 *     its invoices/bills/payments. Defaults to "all active currencies" when
 *     the pivot is empty (legacy behaviour)
 *   - `companies.expense_currency_exchange_account_id` and
 *     `companies.income_currency_exchange_account_id` — FX gain/loss
 *     accounts used when cross-currency reconciliation leaves a base
 *     residual after the foreign-currency residuals close
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('currencies')) {
            Schema::create('currencies', function (Blueprint $table) {
                $table->id();
                $table->string('uuid', 36)->nullable()->unique();
                $table->string('code', 10)->unique();      // ISO 4217 code (IQD, USD, EUR)
                $table->string('name');                    // "Iraqi Dinar", "US Dollar"
                $table->string('symbol', 8)->nullable();   // د.ع, $, €
                // 'before' = "$100"   |   'after' = "100 د.ع"
                $table->enum('position', ['before', 'after'])->default('before');
                // Number of decimal places. 2 for most, 0 for JPY/IQD, 3 for BHD/KWD.
                $table->unsignedTinyInteger('decimal_places')->default(2);
                // Rounding precision (e.g. 0.05 for Swiss Franc cash).
                $table->decimal('rounding', 12, 6)->default(0.01);
                $table->boolean('active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('company_currencies')) {
            Schema::create('company_currencies', function (Blueprint $table) {
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('currency_id')->constrained('currencies')->cascadeOnDelete();
                $table->primary(['company_id', 'currency_id']);
            });
        }

        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'income_currency_exchange_account_id')) {
                $table->foreignId('income_currency_exchange_account_id')
                    ->nullable()->after('accounting_fiscal_year_lock_date')
                    ->constrained('accounts')->nullOnDelete();
            }
            if (!Schema::hasColumn('companies', 'expense_currency_exchange_account_id')) {
                $table->foreignId('expense_currency_exchange_account_id')
                    ->nullable()->after('income_currency_exchange_account_id')
                    ->constrained('accounts')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'expense_currency_exchange_account_id')) {
                $table->dropForeign(['expense_currency_exchange_account_id']);
                $table->dropColumn('expense_currency_exchange_account_id');
            }
            if (Schema::hasColumn('companies', 'income_currency_exchange_account_id')) {
                $table->dropForeign(['income_currency_exchange_account_id']);
                $table->dropColumn('income_currency_exchange_account_id');
            }
        });

        Schema::dropIfExists('company_currencies');
        Schema::dropIfExists('currencies');
    }
};
