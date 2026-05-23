<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_move_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->after('partner_id');
            $table->unsignedBigInteger('uom_id')->nullable()->after('product_id');

            $table->foreign('product_id')->references('id')->on('inventory_products')->nullOnDelete();
            $table->foreign('uom_id')->references('id')->on('inventory_uoms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('account_move_lines', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['uom_id']);
            $table->dropColumn(['product_id', 'uom_id']);
        });
    }
};
