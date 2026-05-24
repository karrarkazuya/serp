<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D1 (Odoo parity): add `date_maturity` to account_move_lines so each AR/AP
 * installment line carries its own due date.
 *
 * Odoo's `account.move.line.date_maturity` enables:
 *   - AR/AP aging reports bucketing receivables by due date, not just by
 *     invoice date
 *   - One invoice with multi-installment payment terms produces ONE receivable
 *     LINE PER INSTALLMENT, each with its own date_maturity — reconciliation
 *     can match payments against specific installments independently
 *   - Dunning / overdue queries work per-installment
 *
 * Existing rows are nulled; the legacy single-counterpart code path treats
 * null as "fall back to the move's invoice_date_due".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_move_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('account_move_lines', 'date_maturity')) {
                $table->date('date_maturity')->nullable()->after('date');
                $table->index('date_maturity', 'account_move_lines_date_maturity_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('account_move_lines', function (Blueprint $table) {
            if (Schema::hasColumn('account_move_lines', 'date_maturity')) {
                $table->dropIndex('account_move_lines_date_maturity_index');
                $table->dropColumn('date_maturity');
            }
        });
    }
};
