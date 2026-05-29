<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odoo parity (`stock.production.lot` / `stock.lot`): expiration tracking is a
 * per-product opt-in. Without this column, the product form's "Track
 * Expiration Date" checkbox could not persist — the column was missing, so
 * LotController had no way to know whether `lots.expiration_date` should be
 * required or merely optional metadata.
 *
 * Now: when a product has `has_expiration_date = true`, the lot create/edit
 * flow elevates `expiration_date` from `nullable` to `required` (see
 * LotController::store/write).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventory_products', function (Blueprint $table) {
            $table->boolean('has_expiration_date')->default(false)->after('tracking');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_products', function (Blueprint $table) {
            $table->dropColumn('has_expiration_date');
        });
    }
};
