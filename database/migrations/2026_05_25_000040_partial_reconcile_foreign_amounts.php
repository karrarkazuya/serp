<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MC4 (Odoo parity): `account.partial.reconcile` in Odoo carries the foreign-
 * currency amount on each side, separately from the base-currency `amount`.
 *
 * Why two columns instead of one: an EUR 100 receivable paid by an EUR 100
 * outbound transfer can match 100 EUR on the foreign side even when the
 * base-currency amounts differ (110 vs 105 because the FX rate moved). The
 * partial reconcile records:
 *   - `amount` = base-currency amount actually reconciled (smaller side)
 *   - `debit_amount_currency`  = how much foreign-currency of the debit line
 *                                this partial consumed
 *   - `credit_amount_currency` = how much foreign-currency of the credit line
 *                                this partial consumed
 *
 * After all partial reconciles, line.amount_currency - sum(my-side foreign
 * amounts) = the FOREIGN residual. When that hits zero, the FX-adjustment
 * code in AccountingService::maybePostFxAdjustment posts the base drift to
 * the company's FX gain/loss account.
 *
 * Backfill: existing rows fill the two new columns with the base `amount`
 * (same-currency reconciles where foreign == base, so the math is identical).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_partial_reconciles', function (Blueprint $table) {
            if (!Schema::hasColumn('account_partial_reconciles', 'debit_amount_currency')) {
                $table->decimal('debit_amount_currency', 18, 4)->nullable()->after('amount');
            }
            if (!Schema::hasColumn('account_partial_reconciles', 'credit_amount_currency')) {
                $table->decimal('credit_amount_currency', 18, 4)->nullable()->after('debit_amount_currency');
            }
        });
    }

    public function down(): void
    {
        Schema::table('account_partial_reconciles', function (Blueprint $table) {
            if (Schema::hasColumn('account_partial_reconciles', 'credit_amount_currency')) {
                $table->dropColumn('credit_amount_currency');
            }
            if (Schema::hasColumn('account_partial_reconciles', 'debit_amount_currency')) {
                $table->dropColumn('debit_amount_currency');
            }
        });
    }
};
