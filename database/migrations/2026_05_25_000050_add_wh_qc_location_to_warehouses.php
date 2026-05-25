<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odoo parity: a 3-step receipt warehouse flows Supplier → Input →
 * Quality Control → Stock. The QC location is the intermediate stop where
 * received goods are inspected before being moved into stock. Adds the
 * `wh_qc_stock_loc_id` column on warehouses to mirror Odoo's
 * `wh_qc_stock_loc_id` field.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_warehouses', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_warehouses', 'wh_qc_stock_loc_id')) {
                $table->foreignId('wh_qc_stock_loc_id')
                    ->nullable()
                    ->after('wh_input_stock_loc_id')
                    ->constrained('inventory_locations')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_warehouses', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_warehouses', 'wh_qc_stock_loc_id')) {
                $table->dropForeign(['wh_qc_stock_loc_id']);
                $table->dropColumn('wh_qc_stock_loc_id');
            }
        });
    }
};
