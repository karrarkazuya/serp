<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Align accounting schema with Odoo's data model:
 *   - O1  invoice_date on account_moves (commercial date, separate from posting date)
 *   - O4  sequence_last_year on account_journals (per-year sequence reset)
 *   - O10 price_include on account_taxes (split from include_base_amount)
 *   - O11 outstanding_receipts_account_id / outstanding_payments_account_id on
 *         account_journals (intermediate "in-process" liquidity accounts that
 *         payments hit before bank reconciliation clears them)
 *
 * Data preservation: for existing rows we copy `invoice_date := date` and
 * `price_include := include_base_amount` so the legacy semantics keep working
 * until callers are migrated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_moves', function (Blueprint $table) {
            if (!Schema::hasColumn('account_moves', 'invoice_date')) {
                // Commercial / customer-facing date. Defaults to the posting `date`
                // for back-compat; callers that don't pass `invoice_date` get the
                // same behaviour as before.
                $table->date('invoice_date')->nullable()->after('date');
            }
        });

        // Backfill: invoice_date := date for every existing row.
        DB::table('account_moves')
            ->whereNull('invoice_date')
            ->update(['invoice_date' => DB::raw('date')]);

        Schema::table('account_journals', function (Blueprint $table) {
            if (!Schema::hasColumn('account_journals', 'sequence_last_year')) {
                // Used by reserveSequenceForJournal: when the move date's year
                // differs from this value, sequence_next_number resets to 1.
                $table->unsignedSmallInteger('sequence_last_year')->nullable()->after('sequence_padding');
            }
            if (!Schema::hasColumn('account_journals', 'outstanding_receipts_account_id')) {
                $table->foreignId('outstanding_receipts_account_id')
                    ->nullable()->after('suspense_account_id')
                    ->constrained('accounts')->nullOnDelete();
            }
            if (!Schema::hasColumn('account_journals', 'outstanding_payments_account_id')) {
                $table->foreignId('outstanding_payments_account_id')
                    ->nullable()->after('outstanding_receipts_account_id')
                    ->constrained('accounts')->nullOnDelete();
            }
        });

        Schema::table('account_taxes', function (Blueprint $table) {
            if (!Schema::hasColumn('account_taxes', 'price_include')) {
                // "Tax is embedded in the line price" — when true, extract net
                // base from the gross price. Separate from include_base_amount,
                // which is the cascading flag ("add this tax to base for the
                // next sequential tax"). Odoo treats these as orthogonal.
                $table->boolean('price_include')->default(false)->after('include_base_amount');
            }
        });

        // Backfill: every existing tax that had the conflated `include_base_amount`
        // flag was being used as "price-inclusive". Copy that semantics to the
        // new `price_include` column so existing taxes keep computing the same
        // result, then clear `include_base_amount` so future cascading-tax
        // semantics aren't already triggered by accident.
        if (Schema::hasColumn('account_taxes', 'price_include')) {
            DB::table('account_taxes')
                ->where('include_base_amount', true)
                ->update([
                    'price_include'       => true,
                    'include_base_amount' => false,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('account_taxes', function (Blueprint $table) {
            if (Schema::hasColumn('account_taxes', 'price_include')) {
                $table->dropColumn('price_include');
            }
        });

        Schema::table('account_journals', function (Blueprint $table) {
            if (Schema::hasColumn('account_journals', 'outstanding_payments_account_id')) {
                $table->dropForeign(['outstanding_payments_account_id']);
                $table->dropColumn('outstanding_payments_account_id');
            }
            if (Schema::hasColumn('account_journals', 'outstanding_receipts_account_id')) {
                $table->dropForeign(['outstanding_receipts_account_id']);
                $table->dropColumn('outstanding_receipts_account_id');
            }
            if (Schema::hasColumn('account_journals', 'sequence_last_year')) {
                $table->dropColumn('sequence_last_year');
            }
        });

        Schema::table('account_moves', function (Blueprint $table) {
            if (Schema::hasColumn('account_moves', 'invoice_date')) {
                $table->dropColumn('invoice_date');
            }
        });
    }
};
