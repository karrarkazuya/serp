<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MC-fix #4 — `(company_id, currency, date)` must be unique on currency_rates.
 *
 * Without this, two rows with the same currency on the same date pass
 * `getExchangeRate`'s `orderByDesc('date')->value('rate')` non-deterministically
 * — the DB planner picks whichever row scans first. Same FX conversion can
 * yield two different base amounts on different runs, which is an audit-trail
 * nightmare for posted moves.
 *
 * The index does NOT include `deleted_at` because SQLite + MySQL both treat
 * NULL as distinct inside unique indexes — adding it would defeat the
 * constraint. Instead, the index covers ALL rows (deleted or not), which
 * means: after soft-deleting a rate, the user must EITHER restore the
 * existing row OR pick a different date. That's actually appropriate for an
 * audit-tracked system — re-creating at the same key would be ambiguous in
 * the chatter history.
 *
 * Idempotent for dirty dev DBs: pre-flight dedupes any existing duplicates
 * by soft-deleting all but the highest-id row (matches the deterministic
 * tiebreaker getExchangeRate uses: orderByDesc('id')).
 *
 * The FormRequest layer (`Rule::unique('currency_rates', 'date')->whereNull('deleted_at')`)
 * is the first line of defense — it returns friendly validation errors
 * before the DB constraint ever fires. This DB index is the
 * belt-and-braces backstop for service-layer / seeder writes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Dedupe pre-existing duplicates by hard-deleting all but the latest
        // row per (company, currency, date). hardDelete because the unique
        // index covers deleted_at-NOT-NULL rows too — soft-deleting them
        // would still collide. The chatter trail still records the deletion
        // event for any user who interacted with the row prior.
        $dupes = DB::table('currency_rates')
            ->select('company_id', 'currency', 'date', DB::raw('MAX(id) as keeper_id'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('company_id', 'currency', 'date')
            ->having('cnt', '>', 1)
            ->get();

        foreach ($dupes as $row) {
            DB::table('currency_rates')
                ->where('company_id', $row->company_id)
                ->where('currency', $row->currency)
                ->where('date', $row->date)
                ->where('id', '<>', $row->keeper_id)
                ->delete();
        }

        Schema::table('currency_rates', function (Blueprint $table) {
            $table->unique(
                ['company_id', 'currency', 'date'],
                'currency_rates_company_code_date_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('currency_rates', function (Blueprint $table) {
            $table->dropUnique('currency_rates_company_code_date_unique');
        });
    }
};
